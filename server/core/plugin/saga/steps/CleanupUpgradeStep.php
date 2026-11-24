<?php

declare(strict_types=1);

namespace core\plugin\saga\steps;

use core\saga\SagaContext;
use support\Log;

/**
 * upgrade saga 最后一步：升级已确认生效，删掉只在回滚时才有用的备份源码树。
 *
 * 必须是独立的最后一步：Reload/VerifyFingerprint 失败时 BackupCurrentStep 的补偿要靠这份备份换回旧版。
 * execute() 不抛异常：此时升级已经成功，删不掉多余目录只记日志，不值得触发整条补偿链。
 */
final class CleanupUpgradeStep extends AbstractPluginSagaStep
{
    public function name(): string
    {
        return 'CleanupUpgrade';
    }

    public function execute(SagaContext $ctx): void
    {
        $backupDir = (string) $ctx->get('backup_dir', '');
        if ($backupDir === '' || $ctx->get('backup_taken') !== true) {
            return;
        }
        $this->rrmdir($backupDir);
        if (is_dir($backupDir)) {
            Log::warning('[plugin-upgrade] 升级成功后未能清理旧版源码备份，需人工清理：' . $backupDir);

            return;
        }
        $ctx->set('backup_taken', false);
    }

    /** 纯清理，无可撤销的副作用；且它成功时整条 saga 已经成功，不会被补偿。 */
    public function compensate(SagaContext $ctx): void
    {
    }

    /** 递归删除目录；软链只断链不跟进。 */
    private function rrmdir(string $dir): void
    {
        if (is_link($dir)) {
            @unlink($dir);

            return;
        }
        if (!is_dir($dir)) {
            return;
        }
        foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $f) {
            $p = "$dir/$f";
            is_dir($p) && !is_link($p) ? $this->rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}
