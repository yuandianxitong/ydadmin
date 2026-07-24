<?php
declare(strict_types=1);

namespace app\command;

use app\service\system\YdSpecCompileService;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;

/**
 * 把已确认的 YdSpec 编译成 DDL + CRUD 代码（暂存到 stage 目录）。
 *
 * 用法：
 *   php think ydspec:compile spec_xxxxxxxxxxxxxxxx
 *   php think ydspec:compile spec_xxxxxxxxxxxxxxxx --apply-dev
 */
class YdSpecCompileCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('ydspec:compile')
            ->setDescription('把已确认的 YdSpec 编译成 DDL + CRUD 代码（暂存到 stage 目录）')
            ->addArgument('spec_id', Argument::REQUIRED, 'spec_id（confirm 后返回）')
            ->addOption('apply-dev', null, Option::VALUE_NONE, '编译后立即把 DDL 刷入 dev 库并写入代码文件（自验用）');
    }

    protected function execute(Input $input, Output $output): int
    {
        $specId  = (string) $input->getArgument('spec_id');
        $service = new YdSpecCompileService();

        $result = $service->compile($specId);
        $output->info('编译完成，stage：' . $result['dir']);
        foreach ($result['files'] as $f) {
            $output->writeln('  ' . $f['path'] . ' (' . $f['bytes'] . ' bytes)');
        }

        if ($input->getOption('apply-dev')) {
            $applied = $service->applyDev($specId, $result['stage_id']);
            $output->info('已执行 DDL，并写入 ' . count($applied['written']) . ' 个文件到项目');
        }

        return 0;
    }
}
