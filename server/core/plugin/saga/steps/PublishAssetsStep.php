<?php

declare(strict_types=1);

namespace core\plugin\saga\steps;

use core\plugin\PluginAssetPublisher;
use core\saga\SagaContext;
use support\Log;

/**
 * install saga 第 3 步 / upgrade saga 第 7 步：把插件的前端产物发布到 public/plugin-assets/。
 *
 * 两条链路正向逻辑相同，只有补偿方向不同：install 失败删光产物（remove），upgrade 失败换回旧版产物（restore），
 * 由 $upgradeMode 区分。
 */
final class PublishAssetsStep extends AbstractPluginSagaStep
{
    /**
     * @param array<string, mixed> $saga
     * @param bool $upgradeMode 见类头：为真时补偿走 `restore()`（换回旧版产物）
     *        而不是 `remove()`（删光）。
     */
    public function __construct(array $saga, private readonly bool $upgradeMode = false)
    {
        parent::__construct($saga);
    }

    public function name(): string
    {
        return 'PublishAssets';
    }

    /**
     * 发布必须排在 SQL 与 MarkEnabled 之前：一落 ENABLED 就开始下发插件清单，产物不到位用户会拿到 404。
     * 无 dist 的 host 跳过。本次真正发布过的 host 显式记进 ctx 的 published_hosts，补偿只回收这些，不靠磁盘反推。
     * 升级模式下先把旧版产物留存一份（{@see archiveOldAssets()}），补偿才有东西可换回。
     */
    public function execute(SagaContext $ctx): void
    {
        $root = $this->pluginRoot();
        $pluginDir = $this->pluginDir();
        try {
            // 取发布集而不是 hosts()：没有经过兼容闸检查的宿主产物不许出现在 web 可访问目录。两者目前相等，但定义不同。
            foreach (PluginAssetPublisher::publishableHosts() as $host) {
                if (!PluginAssetPublisher::hasDist($pluginDir, $host)) {
                    continue;
                }
                if ($this->upgradeMode) {
                    $this->archiveOldAssets($ctx, $host);
                }
                PluginAssetPublisher::publish($root, $pluginDir, $host, $this->code());
                $ctx->push('published_hosts', $host);
            }
        } catch (\Throwable $e) {
            // 必须自己回收：SagaRunner 不补偿抛出的那一步，而发布是逐 host 进行的，前面的 host 可能已上线；
            // 此时 published_hosts 也还没落库。回收失败不掩盖原始异常，只记 warning。
            $errors = $this->rollbackPublished($ctx);
            if ($errors !== []) {
                Log::warning('[plugin-assets] 发布中途失败后回滚产物未完全成功，可能残留：' . implode('; ', $errors));
            }
            throw $e;
        }
    }

    /**
     * 只回收 published_hosts 记账过的 host，不遍历 hosts() 按目录是否存在反推，否则会删掉与本次无关的产物。
     * 单个 host 失败继续处理其余 host，最后抛汇总异常计入 compensationErrors()，清不掉必须让运维看见。
     */
    public function compensate(SagaContext $ctx): void
    {
        $errors = $this->rollbackPublished($ctx);
        if ($errors !== []) {
            throw new \RuntimeException(
                $this->upgradeMode
                    ? '本次换入的新版产物未能退回旧版：' . implode('; ', $errors)
                    : '本次已发布的产物未能清除：' . implode('; ', $errors)
            );
        }
    }

    /**
     * 升级链路专用：换入新版之前，把旧版该 host 的产物留存到 runtime/plugin-assets-backup/<host>/<code>。
     *
     * 源取 BackupCurrentStep 搬走的旧版源码树（ctx 的 backup_dir），此时在用目录已是新版。
     * 必须留存副本而不是回滚时重新 publish：本步补偿先于源码换回执行，那时旧版源码还在 .backup/ 里。
     *
     * 记账分三档：
     *   - assets_archived_hosts：留存成功，补偿时 restore() 换回旧版；
     *   - assets_absent_hosts：旧版本来就没有这个 host 的产物，补偿时 remove()；
     *   - 两者都不在（备份目录不可用）：补偿时不动，否则可能删光仍在线上服务的旧版产物。
     */
    private function archiveOldAssets(SagaContext $ctx, string $host): void
    {
        $backupDir = (string) $ctx->get('backup_dir', '');
        if ($backupDir === '' || !is_dir($backupDir)) {
            Log::warning(sprintf(
                '[plugin-assets] 插件 %s 升级：旧版源码备份不可用（%s），host=%s 的旧版产物无法留存，'
                . '升级失败时这个 host 的产物将保持不动',
                $this->code(),
                $backupDir === '' ? '未记账' : $backupDir,
                $host
            ));

            return;
        }

        $ctx->push(
            PluginAssetPublisher::archive($this->pluginRoot(), $backupDir, $host, $this->code())
                ? 'assets_archived_hosts'
                : 'assets_absent_hosts',
            $host
        );
    }

    /**
     * 回收 published_hosts 记账过的产物；幂等，可被 execute() 的中途失败路径与 compensate() 各调一次。
     * 处理成功的 host 从记账里划掉，没处理成的留着，残留会留在 step_state 里。
     *
     * @return list<string> 每个未能回退的 host 的错误描述
     */
    private function rollbackPublished(SagaContext $ctx): array
    {
        $root = $this->pluginRoot();
        $errors = [];
        $remaining = [];
        $removed = [];
        $undecidable = [];
        foreach ((array) $ctx->get('published_hosts', []) as $host) {
            $host = (string) $host;
            try {
                if (!$this->upgradeMode) {
                    PluginAssetPublisher::remove($root, $host, $this->code());
                    continue;
                }
                if (in_array($host, array_map('strval', (array) $ctx->get('assets_archived_hosts', [])), true)) {
                    if (!PluginAssetPublisher::restore($root, $host, $this->code())) {
                        // 副本不见了也不能退化成 remove()，那会删掉线上仅剩的一份产物。
                        $errors[] = $host . ': 旧版产物副本已不存在，无法换回（线上仍是新版产物）';
                        $remaining[] = $host;
                    }
                    continue;
                }
                if (in_array($host, array_map('strval', (array) $ctx->get('assets_absent_hosts', [])), true)) {
                    PluginAssetPublisher::remove($root, $host, $this->code());
                    $removed[] = $host;
                    continue;
                }
                // 无从判断旧版该有什么，不动（见 archiveOldAssets()）。
                $undecidable[] = $host;
                $remaining[] = $host;
            } catch (\Throwable $inner) {
                $remaining[] = $host;
                $errors[] = $host . ': ' . $inner->getMessage();
                Log::warning('[plugin-assets] 失败后回滚产物失败，可能残留：' . $inner->getMessage());
            }
        }
        $ctx->set('published_hosts', $remaining);

        // 被撤下的 host 不能对运维静默，经 rollback_notes 由 runner 统一写进错误信息。
        if ($removed !== []) {
            $ctx->push('rollback_notes', ' | 旧版不提供以下 host 的前端产物，本次换入的新产物已撤下：' . implode(', ', $removed));
        }
        if ($undecidable !== []) {
            $ctx->push('rollback_notes', ' | 以下 host 的旧版产物无副本可换回，线上仍是新版产物（需人工处置）：' . implode(', ', $undecidable));
        }

        return $errors;
    }
}
