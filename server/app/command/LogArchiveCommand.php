<?php
declare(strict_types=1);

namespace app\command;

use app\model\system\AdminLoginLog;
use app\model\system\AdminOperationLog;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

class LogArchiveCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('log:archive')
            ->setDescription('清理超过指定天数的管理员日志')
            ->addOption('days', 'd', Option::VALUE_OPTIONAL, '保留天数', '90');
    }

    protected function execute(Input $input, Output $output): int
    {
        $days = (int) $input->getOption('days');
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $output->writeln("<info>清理 {$days} 天前的日志（截止时间: {$cutoff}）</info>");

        $opCount = AdminOperationLog::where('operation_time', '<', $cutoff)->count();
        AdminOperationLog::where('operation_time', '<', $cutoff)->delete();

        $loginCount = AdminLoginLog::where('login_time', '<', $cutoff)->count();
        AdminLoginLog::where('login_time', '<', $cutoff)->delete();

        $output->writeln("<info>完成：清理操作日志 {$opCount} 条，登录日志 {$loginCount} 条</info>");

        return 0;
    }
}
