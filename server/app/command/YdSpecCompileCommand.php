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
 * 把已确认的 YdSpec 编译成 DDL + CRUD 代码（暂存 + 自动检查）。
 *
 * 用法：
 *   php think ydspec:compile spec_xxxxxxxxxxxxxxxx
 *   php think ydspec:compile spec_xxxxxxxxxxxxxxxx --apply
 */
class YdSpecCompileCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('ydspec:compile')
            ->setDescription('把已确认的 YdSpec 编译成 DDL + CRUD 代码（暂存 + 自动检查）')
            ->addArgument('spec_id', Argument::REQUIRED, 'spec_id（confirm 后返回）')
            ->addOption('apply', null, Option::VALUE_NONE, '检查通过后经门禁把 DDL 刷入 dev 库并写入代码文件')
            ->addOption('apply-dev', null, Option::VALUE_NONE, '（别名）等同 --apply');
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

        $summary = $result['check_summary'] ?? ['passed' => false, 'results' => []];
        $output->writeln('');
        $output->writeln('检查结果：' . ($summary['passed'] ? '通过' : '未通过')
            . '（error ' . ($summary['error_count'] ?? 0)
            . ' / warning ' . ($summary['warning_count'] ?? 0)
            . ' / skipped ' . count($summary['skipped'] ?? []) . '）');
        foreach ($summary['results'] ?? [] as $r) {
            if (($r['severity'] ?? '') === 'error' || ($r['severity'] ?? '') === 'warning') {
                $output->writeln('  [' . $r['severity'] . '] ' . $r['check'] . '：' . $r['message']);
            }
        }

        $wantApply = $input->getOption('apply') || $input->getOption('apply-dev');
        if ($wantApply) {
            if (empty($summary['passed'])) {
                $output->error('门禁未通过，拒绝应用。请修正后重编译。');
                return 1;
            }
            $applied = $service->applyDev($specId, $result['stage_id']);
            $output->info('已通过门禁并写入 ' . count($applied['written']) . ' 个文件到项目');
        }

        return 0;
    }
}
