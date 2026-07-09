<?php
// server/app/command/YdAiCommand.php
declare(strict_types=1);

namespace app\command;

use core\ai\AiClient;
use core\ai\AiClientException;
use core\ai\DiffPreview;
use core\ai\FeedbackReporter;
use core\ai\FileWriter;
use core\ai\ProjectContext;
use core\ai\SchemaException;
use core\ai\SchemaReader;
use core\ai\YdConfig;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;

class YdAiCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('yd:ai')
            ->setDescription('用自然语言生成符合元点框架规范的代码')
            ->addArgument('instruction', Argument::REQUIRED, '需求描述（自然语言）')
            ->addOption('tables', 't', Option::VALUE_REQUIRED, '相关数据表，逗号分隔')
            ->addOption('layers', null, Option::VALUE_REQUIRED, '生成层次', 'controller,service,repository,model')
            ->addOption('dry-run', null, Option::VALUE_NONE, '只生成与预览，不写入')
            ->addOption('write', null, Option::VALUE_NONE, '跳过确认直接写入')
            ->addOption('export-json', null, Option::VALUE_REQUIRED, '额外导出 files.json 到指定目录（评测用）');
    }

    protected function execute(Input $input, Output $output): int
    {
        $instruction = (string) $input->getArgument('instruction');
        $tables = array_filter(array_map('trim', explode(',', (string) $input->getOption('tables'))));
        if (empty($tables)) {
            $output->error('请用 --tables=表名 指定相关数据表（多表用逗号分隔）');
            return 1;
        }
        $layers = array_filter(array_map('trim', explode(',', (string) $input->getOption('layers'))));

        $config = new YdConfig();
        $token = env('YD_AI_TOKEN') ?: $config->get('token');
        $client = new AiClient((string) config('ai.endpoint'), $token ?: null, (int) config('ai.timeout'));
        $projectRoot = rtrim(root_path(), '/') . '/..' ; // server/ 的上级 = 项目根
        $projectRoot = realpath($projectRoot) ?: dirname(root_path());
        $writer = new FileWriter($projectRoot);
        $writer->cleanupStale();

        try {
            $schemaInput = (new SchemaReader())->buildSchemaInput($tables);
            $output->info('正在请求 AI 引擎生成（流式输出）…');
            $result = $client->generate($instruction, (new ProjectContext())->id(), $layers, $schemaInput,
                fn (string $chunk) => $output->write($chunk));
            $output->writeln('');
        } catch (AiClientException $e) {
            $output->error($e->getMessage());
            return $this->exitCodeFor($e);
        }

        $files = $result['files'] ?? [];
        if (empty($files)) {
            $output->error('引擎未返回任何文件');
            return 2;
        }

        $temp = $writer->stageToTemp($files);
        foreach ($writer->getSkipped() as $bad) {
            $output->warning("已拒绝不安全路径：{$bad}");
        }

        if ($exportDir = $input->getOption('export-json')) {
            if (!is_dir($exportDir)) {
                mkdir($exportDir, 0755, true);
            }
            file_put_contents(rtrim($exportDir, '/') . '/files.json',
                json_encode($files, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $output->info("已导出评测格式：{$exportDir}/files.json");
        }

        $output->writeln((new DiffPreview($projectRoot))->render($temp, $files));
        $output->info('临时目录：' . $temp . '（24 小时后自动清理）');

        if ($input->getOption('dry-run')) {
            return 0;
        }

        $reporter = new FeedbackReporter($config, $client);
        if ($this->shouldAskOptin($input)) {
            $reporter->resolveOptin(fn (string $q) => strtolower(trim((string) $output->ask($input, $q . ' [y/N]'))) === 'y');
        }

        $confirmed = $input->getOption('write')
            || strtolower(trim((string) $output->ask($input, '写入以上 ' . count($files) . ' 个文件？[y/N]'))) === 'y';
        if (!$confirmed) {
            $output->comment('已取消，未写入任何文件');
            $reporter->report((string) ($result['generation_id'] ?? ''), 'rejected');
            return 0;
        }

        $written = $writer->commit($temp, $files);
        foreach ($written as $path) {
            $output->info("  已写入 {$path}");
        }
        $output->info('完成。提醒：新菜单需手动加入 server/public/install/data/init.sql（检查已用 ID）');
        $reporter->report((string) ($result['generation_id'] ?? ''), 'accepted');
        return 0;
    }

    /**
     * 是否需要询问反馈 opt-in：--write 直接写入模式或非交互环境（stdin 不可用，如 CI）下跳过询问，
     * 避免 $output->ask() 在 stdin EOF 时抛 RuntimeException('Aborted') 导致进程崩溃。
     * 跳过时 FeedbackReporter::report() 因 config 未设置 optin 会自然静默，不产生副作用。
     */
    protected function shouldAskOptin(Input $input): bool
    {
        return !$input->getOption('write') && $input->isInteractive();
    }

    /**
     * 异常类型 → 退出码：SchemaException（数据表读取失败，用户输入问题）→ 1；
     * 其余 AiClientException（引擎连接/响应异常）→ 2。避免依赖不可控的远端错误文案做 str_contains 判断。
     */
    protected function exitCodeFor(AiClientException $e): int
    {
        return $e instanceof SchemaException ? 1 : 2;
    }
}
