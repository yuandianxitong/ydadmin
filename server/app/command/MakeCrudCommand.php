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
 * --force：GeneratorService::generate() 的签名里没有覆盖选项（那是跨任务的固定契约），这里的做法是
 * 先用 GeneratorService::targetPaths() 拿到本次会用到的全部磁盘绝对路径，除 page 外把已存在的文件
 * 删掉，再调用 generate()——对它而言这些路径此刻就是"不存在"，会按正常流程写入。页面路径永远不删、
 * 不覆盖。（注意：不能用 preview() 的返回路径做这一步——那是给前端展示用的相对仓库根路径，直接
 * is_file()/unlink() 会被命令的当前工作目录带偏，见 targetPaths() 的方法注释。）
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
            ->addOption('force', null, InputOption::VALUE_NONE, '允许覆盖已存在文件（两个前端页面文件永远不覆盖）')
            ->addOption('reload', null, InputOption::VALUE_NONE, '生成后执行一次 php start.php reload');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $table = (string) $input->getArgument('table');
        $module = (string) ($input->getOption('module') ?? '');
        $modelOption = (string) ($input->getOption('model') ?? '');
        $commentOption = (string) ($input->getOption('comment') ?? '');

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
        $comment = $commentOption !== '' ? $commentOption : (string) $tableRow['comment'];

        $request = new GeneratorRequest($table, $module, $model, $comment, []);

        try {
            if ($input->getOption('preview')) {
                $output->writeln('<info>产物清单（预览，未落盘）：</info>');
                foreach ($service->preview($request) as $key => $artifact) {
                    $output->writeln(sprintf('  %-12s %s', $key, $artifact['path']));
                }

                return self::SUCCESS;
            }

            if ($input->getOption('force')) {
                // targetPaths() 给的是磁盘绝对路径，不是 preview() 那种给前端展示用的相对路径——
                // 用相对路径直接 is_file()/unlink() 会被当前工作目录（server/）带偏，见方法注释。
                foreach ($service->targetPaths($request) as $key => $absolutePath) {
                    if ($key === 'page' || $key === 'form') {
                        // 两个前端页面文件永远不覆盖：列表页与表单组件都是人手改过就回不来的东西
                        // （加联动字段、改校验触发时机），而 --force 是 unlink，没有备份也没有二次确认。
                        // 判错方向最多让 --force 少删一个文件（用户自己删即可），判反是不可逆的数据丢失。
                        continue;
                    }
                    if (is_file($absolutePath)) {
                        unlink($absolutePath);
                    }
                }
            }

            $result = $service->generate($request);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $message) {
                $output->writeln("<error>{$field}：{$message}</error>");
            }

            return self::FAILURE;
        } catch (BusinessException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return self::FAILURE;
        }

        foreach ($result['files'] as $file) {
            $mark = match ($file['status']) {
                'created' => '<info>created</info> ',
                'skipped' => '<comment>skipped</comment>',
                default   => '<error>failed </error>',
            };
            $line = "  {$mark} {$file['path']}";
            if (isset($file['reason'])) {
                $line .= " ({$file['reason']})";
            }
            $output->writeln($line);
        }
        $output->writeln('<info>路由文件已生成，需要 php start.php reload 后接口才生效。</info>');

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
