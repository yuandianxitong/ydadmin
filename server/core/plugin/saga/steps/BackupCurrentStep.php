<?php

declare(strict_types=1);

namespace core\plugin\saga\steps;

use core\exception\BusinessException;
use core\plugin\PluginFiles;
use core\plugin\PluginPaths;
use core\saga\SagaContext;
use support\Log;

/**
 * upgrade saga 第 3 步：把在用的旧版源码树整棵搬进 plugins/.backup/<code>-<ver>-<ts>/。
 *
 * PluginScanner 跳过点开头目录，.backup/ 不会被当成插件装载。
 */
final class BackupCurrentStep extends AbstractPluginSagaStep
{
    public function name(): string
    {
        return 'BackupCurrent';
    }

    /**
     * 本步失败时 SagaRunner 不会执行后面的 SwapNewVersionStep，“备份没成功就不删源码”由步骤顺序保证。
     * mkdir()/rename() 的返回值都要判：失败说明旧版源码仍在原处，一个字节都不许删，报错要说清源码在哪。
     * backup_dir 含运行期时间戳，必须写进 ctx 随 step_state 落库，崩溃恢复才能找到备份。
     */
    public function execute(SagaContext $ctx): void
    {
        $pluginDir = $this->pluginDir();
        if (!is_dir($pluginDir)) {
            // 排队期间目录可能已消失，必须重判：没备份就继续升级的话，任何一步失败都退不回去。
            throw new BusinessException(
                sprintf(
                    '插件目录在升级排队期间消失：%s（没有旧版源码可备份，此时继续升级'
                    . '等于放弃整条回滚路径）',
                    $pluginDir
                ),
                409
            );
        }

        $backupRoot = PluginPaths::root() . '/.backup';
        $backupDir = $backupRoot . '/' . PluginFiles::backupDirName(
            $this->code(),
            (string) $ctx->get('from_version', '')
        );

        error_clear_last();
        if (!is_dir($backupRoot) && !@mkdir($backupRoot, 0o755, true) && !is_dir($backupRoot)) {
            throw new BusinessException(
                "备份目录创建失败（{$backupRoot}）：" . PluginFiles::lastErrorReason()
                . "；旧版源码未被移动，仍完整保留在 {$pluginDir}",
                500
            );
        }
        error_clear_last();
        if (!@rename($pluginDir, $backupDir)) {
            throw new BusinessException(
                "备份旧版失败（{$pluginDir} → {$backupDir}）：" . PluginFiles::lastErrorReason()
                . '；旧版源码未被移动，仍完整保留在原处',
                500
            );
        }

        $ctx->set('backup_dir', $backupDir);
        $ctx->set('backup_taken', true);
    }

    /**
     * 把备份的旧版源码树换回在用位置。
     *
     * 换不回去时代码目录是空的，置 source_restore_failed 让 MarkUpgradingStep::compensate() 落 FAILED 而不是 ENABLED，
     * 并抛出计入 compensationErrors()（退出码 2，需人工介入），报错说清备份在哪。
     */
    public function compensate(SagaContext $ctx): void
    {
        if ($ctx->get('backup_taken') !== true) {
            return;
        }
        $backupDir = (string) $ctx->get('backup_dir', '');
        $pluginDir = $this->pluginDir();

        if (is_dir($pluginDir)) {
            // 新版目录没被删掉时不能 rename，也不能删它：此刻不知道里面放的是什么。
            $ctx->set('source_restore_failed', true);
            $ctx->push('rollback_notes', sprintf(
                ' | 严重：旧版源码未能从备份换回（%s 仍被占用），旧版源码仍完整保存在 %s，'
                . '请人工移回后再启用（插件已置为 FAILED）',
                $pluginDir,
                $backupDir
            ));
            throw new \RuntimeException("旧版源码未能换回：{$pluginDir} 仍被占用，备份仍在 {$backupDir}");
        }

        error_clear_last();
        if (@rename($backupDir, $pluginDir)) {
            $ctx->set('backup_taken', false);

            return;
        }

        $reason = PluginFiles::lastErrorReason();
        $ctx->set('source_restore_failed', true);
        $ctx->push('rollback_notes', sprintf(
            ' | 严重：旧版源码未能从备份换回（%s），代码目录 %s 当前不可用；'
            . '旧版源码仍完整保存在 %s，请人工移回后再启用（插件已置为 FAILED）',
            $reason,
            $pluginDir,
            $backupDir
        ));
        Log::error("[plugin-upgrade] 插件 {$this->code()} 旧版源码未能从备份换回：{$reason}");

        throw new \RuntimeException("旧版源码未能从备份换回（{$reason}），备份仍在 {$backupDir}");
    }
}
