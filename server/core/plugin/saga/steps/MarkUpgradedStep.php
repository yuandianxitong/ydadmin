<?php

declare(strict_types=1);

namespace core\plugin\saga\steps;

use core\exception\BusinessException;
use core\plugin\contracts\PluginStateListener;
use core\plugin\contracts\PluginStore;
use core\plugin\contracts\RouteGenerationStore;
use core\plugin\PluginFiles;
use core\plugin\PluginStatus;
use core\runtime\RuntimePaths;
use core\saga\SagaContext;
use support\Db;

/**
 * upgrade saga 第 8 步：把升级包收进 runtime/plugin-packages/，并把 plugins 行推进到新版本 + ENABLED。
 *
 * 它是整条链上唯一改写 plugins.version 的地方，ReloadStep 必须排在它之后才能算出正确的期望指纹；
 * Reload/Verify 失败时版本号也必须退回去，所以单独成步且可补偿。
 */
final class MarkUpgradedStep extends AbstractPluginSagaStep
{
    public function __construct(
        array $saga,
        private readonly PluginStore $pluginRepo,
        private readonly PluginStateListener $stateListener,
        private readonly RouteGenerationStore $generation,
    ) {
        parent::__construct($saga);
    }

    /**
     * 补偿时需要还原的列：本步写哪几列，就精确还原哪几列。
     *
     * @return list<string>
     */
    private function restoredColumns(): array
    {
        return [
            'version',
            'name',
            'description',
            'manifest',
            'depends',
            ...array_keys($this->stateListener->enabledColumns($this->code(), [])),
            'read_safe_routes',
            'package_path',
            'package_hash',
        ];
    }

    public function name(): string
    {
        return 'MarkUpgraded';
    }

    /**
     * 先快照再写入：补偿要把这几列精确还原成升级前的值，不能只靠 payload 里的 from_version 反推版本号。
     * zip 用 rename() 收编：源是请求内落到 staging/ 的自有拷贝，同一文件系统，不会撞上 EXDEV。
     */
    public function execute(SagaContext $ctx): void
    {
        /** @var array<string, mixed> $manifest */
        $manifest = (array) $ctx->get('manifest', []);
        $toVersion = (string) $ctx->get('to_version', '');
        $stagedZip = (string) ($this->payload()['staged_zip'] ?? '');

        $before = $this->pluginRepo->find($this->pluginId());
        if ($before === null) {
            throw new BusinessException('插件不存在（已在升级期间被删除）', 404);
        }
        $snapshot = [];
        foreach ($this->restoredColumns() as $column) {
            $snapshot[$column] = $before[$column] ?? null;
        }
        $ctx->set('plugin_row_before_upgrade', $snapshot);

        $packageDir = RuntimePaths::pluginPackagesDir($this->pluginRoot());
        if (!is_dir($packageDir)) {
            mkdir($packageDir, 0o755, true);
        }
        $packagePath = $packageDir . '/' . $this->code() . '-' . $toVersion . '.zip';
        error_clear_last();
        if (!@rename($stagedZip, $packagePath)) {
            throw new BusinessException('zip 备份失败：' . PluginFiles::lastErrorReason(), 500);
        }

        // 算不出 hash 必须抛错，不能写成 `?: ''`：package_hash 为空时重装会跳过包完整性校验，闸门永久失效且无痕。
        // 宁可让整条升级补偿回滚。用 @ + lastErrorReason() 把 errno 原因带进异常，避免 warning 触发 failOnWarning。
        error_clear_last();
        $packageHash = @hash_file('sha256', $packagePath);
        if ($packageHash === false) {
            throw new BusinessException(
                "升级包收编后无法计算 sha256（{$packagePath}）：" . PluginFiles::lastErrorReason()
                . '；拒绝把空值写进 package_hash——空的 package_hash 会让将来重装时的包完整性校验被静默跳过',
                500
            );
        }

        $depends = array_values(array_filter(array_map('strval', (array) ($manifest['depends'] ?? []))));

        // 状态写入与版本号加一同一事务：UPGRADING → ENABLED 进入 ENABLED，加一。
        $beforeStatus = (int) ($before['status'] ?? PluginStatus::UPGRADING);
        Db::connection()->transaction(function () use (
            $beforeStatus, $toVersion, $manifest, $depends, $packagePath, $packageHash
        ): void {
            $this->pluginRepo->update($this->pluginId(), [
                ...$this->stateListener->enabledColumns($this->code(), $manifest),
                'version' => $toVersion,
                'name' => (string) ($manifest['name'] ?? $this->code()),
                'description' => (string) ($manifest['description'] ?? ''),
                'manifest' => json_encode($manifest, JSON_UNESCAPED_UNICODE),
                'depends' => $depends !== [] ? json_encode($depends, JSON_UNESCAPED_UNICODE) : null,
                'read_safe_routes' => isset($manifest['read_safe_routes']) && is_array($manifest['read_safe_routes'])
                    ? json_encode(array_values($manifest['read_safe_routes']), JSON_UNESCAPED_UNICODE)
                    : null,
                'package_path' => $packagePath,
                'package_hash' => $packageHash,
                'status' => PluginStatus::ENABLED,
                'last_error' => '',
            ]);
            if ($this->generation->shouldBump($beforeStatus, PluginStatus::ENABLED, false)) {
                $this->generation->bump();
            }
        });
        $ctx->set('plugin_row_upgraded', true);
    }

    /**
     * 把 manifest 派生的列还原成升级前的快照，状态退回 UPGRADING 而不是 ENABLED：
     * 由补偿链里排在后面的 MarkUpgradingStep 按源码是否换回决定落 ENABLED 还是 FAILED，它只认 status=UPGRADING。
     * 收编进 runtime/plugin-packages/ 的 zip 是该版本的归档，不删。
     */
    public function compensate(SagaContext $ctx): void
    {
        if ($ctx->get('plugin_row_upgraded') !== true) {
            return;
        }
        /** @var array<string, mixed> $snapshot */
        $snapshot = (array) $ctx->get('plugin_row_before_upgrade', []);
        if ($snapshot === []) {
            throw new \RuntimeException(
                'plugins 行升级前的快照丢失，无法把 version/manifest 等列还原到升级前，需人工比对'
            );
        }
        $snapshot['status'] = PluginStatus::UPGRADING;

        // ENABLED → UPGRADING 是离开 ENABLED：execute() 加过一次，这里必须对称地再加一次，
        // 否则 MarkUpgrading 补偿落 FAILED（源码换不回来）时没有任何后续写入会让版本号变化。
        Db::connection()->transaction(function () use ($snapshot): void {
            $this->pluginRepo->update($this->pluginId(), $snapshot);
            if ($this->generation->shouldBump(PluginStatus::ENABLED, PluginStatus::UPGRADING, false)) {
                $this->generation->bump();
            }
        });
        $ctx->set('plugin_row_upgraded', false);
    }
}
