<?php

declare(strict_types=1);

namespace core\plugin\saga\steps;

use core\exception\BusinessException;
use core\plugin\PluginPackageInstaller;
use core\saga\SagaContext;

/**
 * upgrade saga 第 4 步：把新版 zip 解压到在用位置 `plugins/<code>/`。
 */
final class SwapNewVersionStep extends AbstractPluginSagaStep
{
    public function __construct(array $saga, private readonly PluginPackageInstaller $installer)
    {
        parent::__construct($saga);
    }

    public function name(): string
    {
        return 'SwapNewVersion';
    }

    /**
     * 只有本次真正解压过才置 extracted_by_this_run，补偿只删这种情况下的目录：备份失败时在用位置仍是旧版源码，
     * 删掉不可逆。标志随 step_state 落库，进程被杀后的崩溃恢复也能读到。
     * extract() 拒绝解压到已存在的目录，这同时校验了备份那一步确实把旧版移走了。
     */
    public function execute(SagaContext $ctx): void
    {
        $stagedZip = (string) ($this->payload()['staged_zip'] ?? '');
        if ($stagedZip === '' || !is_file($stagedZip)) {
            // 与备份之间隔着真实 I/O，文件可能被清理脚本拿走，重判一次给出明确报错。
            throw new BusinessException("升级包已不存在，无法解压新版：{$stagedZip}", 409);
        }

        $this->installer->extract($stagedZip, $this->pluginDir());
        $ctx->set('extracted_by_this_run', true);
    }

    /**
     * 删掉本次解压出来的新版目录，给 BackupCurrentStep::compensate() 腾出在用位置。
     * rrmdir() 删不掉时不出声，所以事后核对：目录还在就抛出，计入 compensationErrors()，
     * 否则后续的 rename 必然失败而台账只写着“已补偿”。
     */
    public function compensate(SagaContext $ctx): void
    {
        if ($ctx->get('extracted_by_this_run') !== true) {
            return;
        }
        $this->rrmdir($this->pluginDir());

        if (is_dir($this->pluginDir())) {
            throw new \RuntimeException(
                '本次解压出来的新版插件目录未能删除，旧版源码将无法换回：' . $this->pluginDir()
            );
        }
        $ctx->set('extracted_by_this_run', false);
    }

    /**
     * 递归删除目录。必须先判 is_link()：is_dir() 对指向目录的软链返回 true，会删到插件目录之外。
     */
    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir) || is_link($dir)) {
            return;
        }
        foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $f) {
            $p = "$dir/$f";
            is_dir($p) && !is_link($p) ? $this->rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}
