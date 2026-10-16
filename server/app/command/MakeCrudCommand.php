<?php

declare(strict_types=1);

namespace app\command;

use app\service\system\GeneratorService;
use core\exception\BusinessException;
use core\exception\ValidationException;
use core\generator\GeneratorRequest;
use core\generator\NameConvention;
use support\Container;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * CLI 版代码生成器：复用 GeneratorService，跳过 HTTP 层（spec §5.5）。
 *
 * 默认只创建新文件；--update 通过可信基线三方合并；--force 在备份后替换已有后端等产物，保留页面与表单。
 * 所有模式均由 GeneratorService 校验并完整渲染，不提前删除文件。--baseline-dir 只导入原始生成快照。
 *
 * module_name/model_name 的正则校验：命令绕开了 GeneratorController::validate()，这里原样重判一次
 * （规则来自 spec §9.1，与控制器保持一致）。GeneratorService 内部仍有同一套校验（含保留模块名与
 * 生产禁用），命令这里的判断只是提前给出更友好的提示，不是唯一防线，也不能替代 Service 层那一份。
 */
#[AsCommand('make:crud', '按已有数据表生成一套 CRUD 模块（复用代码生成器）')]
final class MakeCrudCommand extends Command
{
    private const MODULE_PATTERN = '/^[a-z][a-z0-9_]{0,30}$/';

    private const MODEL_PATTERN = '/^[A-Z][A-Za-z0-9]{0,40}$/';

    /**
     * --comment 的字符集与长度限制，与 GeneratorController::generatorRules() 的 table_comment
     * 规则同义（那边是 max:100|regex:/^[^\r\n<>]*$/u）。命令绕开了控制器的 validate()，不在这里
     * 补一道就完全没有上限、也没有字符集限制。单引号刻意不禁：中文说明里出现撇号合法且常见，
     * 由模板侧按落点转义（ModuleBlueprint::vars() 的 tableCommentJs/Sql）负责。
     */
    private const COMMENT_PATTERN = '/^[^\r\n<>]*$/u';

    private const COMMENT_MAX_LENGTH = 100;

    protected function configure(): void
    {
        $this->addArgument('table', InputArgument::REQUIRED, '已存在的数据表名')
            ->addOption('module', null, InputOption::VALUE_REQUIRED, '模块名（必填，^[a-z][a-z0-9_]{0,30}$，不能用系统保留的模块名）')
            ->addOption('model', null, InputOption::VALUE_REQUIRED, '模型名（^[A-Z][A-Za-z0-9]{0,40}$），缺省由表名推断')
            ->addOption('comment', null, InputOption::VALUE_REQUIRED, '模块中文说明，缺省取表注释')
            ->addOption('preview', null, InputOption::VALUE_NONE, '只打印产物清单，不落盘')
            ->addOption('update', null, InputOption::VALUE_NONE, '按生成基线增量合并；遇到冲突保留全部源文件')
            ->addOption('plan-token', null, InputOption::VALUE_REQUIRED, '使用已审阅计划的令牌执行 --update，内容变化时拒绝写入')
            ->addOption('json', null, InputOption::VALUE_NONE, '与 --update --preview 合用，输出完整 JSON 计划及合并内容')
            ->addOption('baseline-dir', null, InputOption::VALUE_REQUIRED, '从原始生成快照的仓库根导入基线，本次不更新源文件')
            ->addOption('force', null, InputOption::VALUE_NONE, '允许覆盖已存在文件（两个前端页面文件永远不覆盖）')
            ->addOption('reload', null, InputOption::VALUE_NONE, '生成后执行一次 php start.php reload');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $table = (string) $input->getArgument('table');
        $module = (string) ($input->getOption('module') ?? '');
        $modelOption = (string) ($input->getOption('model') ?? '');
        $commentOption = (string) ($input->getOption('comment') ?? '');

        if ($input->getOption('baseline-dir') !== null && ($input->getOption('update') || $input->getOption('force') || $input->getOption('preview')
            || $input->getOption('json') || $input->getOption('plan-token') !== null || $input->getOption('reload'))) {
            $output->writeln('<error>--baseline-dir 是独立的基线导入操作，不能与更新、覆盖、预览或重载选项混用</error>');
            return self::FAILURE;
        }

        if ($input->getOption('force') && $input->getOption('update')) {
            $output->writeln('<error>--force 与 --update 不能同时使用</error>');
            return self::FAILURE;
        }
        if (($input->getOption('json') && (!$input->getOption('update') || !$input->getOption('preview')))
            || ($input->getOption('plan-token') !== null && (!$input->getOption('update') || $input->getOption('preview')
                || preg_match('/^[a-f0-9]{64}\z/', (string) $input->getOption('plan-token')) !== 1))) {
            $output->writeln('<error>--json 需要 --update --preview；--plan-token 仅用于执行 --update 且须为 64 位十六进制</error>');
            return self::FAILURE;
        }

        // --module 没有默认值：生成器不替调用方猜一个会变成文件路径与 PHP 命名空间的名字，
        // 必须显式传（与 GeneratorController 那边前端表单默认值 business 会被同一道保留字
        // 校验拦下是同一件事，但 CLI 自己的默认值没有理由重蹈这个坑，所以干脆不设默认值）。
        if ($module === '') {
            $output->writeln('<error>--module 必填：生成器不替你猜模块名，且不能用系统保留的模块名（比如 business）</error>');

            return self::FAILURE;
        }
        if (preg_match(self::MODULE_PATTERN, $module) !== 1) {
            $output->writeln("<error>--module 不合法：{$module}（须匹配 ^[a-z][a-z0-9_]{0,30}\$）</error>");

            return self::FAILURE;
        }

        $service = Container::get(GeneratorService::class);

        $tableRow = null;
        foreach ($service->getTables() as $row) {
            if ($row['name'] === $table) {
                $tableRow = $row;
                break;
            }
        }
        if ($tableRow === null) {
            $output->writeln("<error>表不存在：{$table}</error>");

            return self::FAILURE;
        }

        $model = $modelOption !== '' ? $modelOption : (new NameConvention())->modelFromTable($table);
        if (preg_match(self::MODEL_PATTERN, $model) !== 1) {
            $output->writeln("<error>--model 不合法：{$model}（须匹配 ^[A-Z][A-Za-z0-9]{0,40}\$）</error>");

            return self::FAILURE;
        }
        // 只校验显式传进来的值。缺省取的是库里的表注释，那条路径由模板侧按落点转义兜底
        // （ModuleBlueprint::vars()）——不该因为某张表的注释里带了个尖括号，就让 make:crud 整个跑不起来。
        if ($commentOption !== ''
            && (mb_strlen($commentOption) > self::COMMENT_MAX_LENGTH || preg_match(self::COMMENT_PATTERN, $commentOption) !== 1)) {
            $output->writeln('<error>--comment 不合法：' . lang('generator.invalid_table_comment') . '</error>');

            return self::FAILURE;
        }
        $comment = $commentOption !== '' ? $commentOption : (($input->getOption('update') || $input->getOption('baseline-dir') !== null) ? '' : (string) $tableRow['comment']);

        $request = new GeneratorRequest($table, $module, $model, $comment, []);

        try {
            if ($input->getOption('baseline-dir') !== null) {
                $imported = $service->importBaseline($request, (string) $input->getOption('baseline-dir'));
                $output->writeln('已导入 ' . $imported['imported'] . ' 个原始生成产物作为基线，源文件未修改。');
                return self::SUCCESS;
            }
            if ($input->getOption('update')) {
                $plan = $service->plan($request);
                $result = $input->getOption('preview') ? $plan : $service->updateGenerated($request, (string) ($input->getOption('plan-token') ?? $plan['plan_token']));
                if ($input->getOption('json')) {
                    $output->writeln(json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), OutputInterface::OUTPUT_RAW);
                    return $result['has_conflicts'] ? self::FAILURE : self::SUCCESS;
                }
                foreach ($result['files'] as $file) {
                    $output->writeln($file['status'] . ' ' . $file['path'] . ($file['reason'] !== '' ? ' — ' . $file['reason'] : ''), OutputInterface::OUTPUT_RAW);
                }
                $output->writeln('plan_token: ' . $result['plan_token'], OutputInterface::OUTPUT_RAW);
                if (isset($result['conflict_directory'])) {
                    $output->writeln('conflicts: ' . $result['conflict_directory'], OutputInterface::OUTPUT_RAW);
                }
                if (isset($result['backup_directory'])) {
                    $output->writeln('backup: ' . $result['backup_directory'], OutputInterface::OUTPUT_RAW);
                }
                if ($result['has_conflicts']) {
                    return self::FAILURE;
                }
                if ($input->getOption('preview')) {
                    return self::SUCCESS;
                }
                $output->writeln('<info>增量更新完成，控制器路由在 php start.php reload 后生效。</info>');
                // 复用后面的显式 --reload 处理。
                $result = ['files' => []];
            } elseif ($input->getOption('preview')) {
                $output->writeln('<info>产物清单（预览，未落盘）：</info>');
                foreach ($service->preview($request) as $key => $artifact) {
                    $output->writeln(sprintf('  %-12s %s', $key, $artifact['path']));
                }

                return self::SUCCESS;
            } else {
                // 先完整渲染，再备份与替换。禁止提前 unlink，模板失败也不会损坏已有源码。
                $result = $input->getOption('force') ? $service->forceGenerate($request) : $service->generate($request);
            }
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $message) {
                $output->writeln("<error>{$field}：{$message}</error>");
            }

            return self::FAILURE;
        } catch (BusinessException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return self::FAILURE;
        } catch (\RuntimeException $e) {
            $output->writeln($e->getMessage(), OutputInterface::OUTPUT_RAW);
            return self::FAILURE;
        }

        foreach ($result['files'] as $file) {
            $mark = match ($file['status']) {
                'created' => '<info>created</info> ',
                'updated' => '<info>updated</info> ',
                'skipped' => '<comment>skipped</comment>',
                default   => '<error>failed </error>',
            };
            $line = "  {$mark} {$file['path']}";
            if (isset($file['reason'])) {
                $line .= " ({$file['reason']})";
            }
            $output->writeln($line);
        }
        $output->writeln('<info>控制器路由在 php start.php reload 后生效。</info>');

        if ($input->getOption('reload')) {
            $output->writeln('<info>正在执行 php start.php reload……</info>');
            exec('cd ' . escapeshellarg(base_path()) . ' && php start.php reload 2>&1', $reloadOutput, $exitCode);
            foreach ($reloadOutput as $line) {
                $output->writeln('  ' . $line);
            }
            if ($exitCode !== 0) {
                $output->writeln('<comment>reload 未成功执行（退出码 ' . $exitCode . '），请手动执行 php start.php reload</comment>');
            }
        }

        return self::SUCCESS;
    }
}
