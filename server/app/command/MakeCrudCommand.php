<?php
declare(strict_types=1);

namespace app\command;

use app\service\system\CodeGeneratorService;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Argument;
use think\console\input\Option;

/**
 * CRUD 代码生成器 CLI 命令
 *
 * 用法:
 *   php think make:crud articles --module=content --model=Article
 *   php think make:crud articles --module=content --model=Article --preview
 *   php think make:crud articles --module=content --model=Article --force
 */
class MakeCrudCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('make:crud')
            ->setDescription('从数据表生成完整的 CRUD 代码（Controller/Service/Repository/Model/Validate/Route/前端页面）')
            ->addArgument('table', Argument::REQUIRED, '数据表名')
            ->addOption('module', 'm', Option::VALUE_REQUIRED, '模块名（如 system, content, business）', 'business')
            ->addOption('model', null, Option::VALUE_REQUIRED, '模型名（大驼峰，如 Article），默认从表名推断')
            ->addOption('comment', 'c', Option::VALUE_REQUIRED, '模块中文说明（如 文章），默认取表注释')
            ->addOption('preview', 'p', Option::VALUE_NONE, '仅预览文件列表，不实际写入')
            ->addOption('force', 'f', Option::VALUE_NONE, '覆盖已有文件');
    }

    protected function execute(Input $input, Output $output): int
    {
        $tableName  = $input->getArgument('table');
        $moduleName = $input->getOption('module');
        $modelName  = $input->getOption('model');
        $comment    = $input->getOption('comment');
        $preview    = $input->getOption('preview');
        $force      = $input->getOption('force');

        $service = new CodeGeneratorService();

        // 验证表是否存在
        try {
            $columns = $service->getTableColumns($tableName);
        } catch (\Exception $e) {
            $output->error("数据表 '{$tableName}' 不存在或无法访问");
            return 1;
        }

        if (empty($columns)) {
            $output->error("数据表 '{$tableName}' 没有字段信息");
            return 1;
        }

        // 自动推断模型名
        if (empty($modelName)) {
            $modelName = $this->tableToModel($tableName);
        }

        // 自动推断注释
        if (empty($comment)) {
            $comment = $this->getTableComment($tableName) ?: $modelName;
        }

        $output->writeln('');
        $output->info('╔══════════════════════════════════════╗');
        $output->info('║       CRUD 代码生成器                ║');
        $output->info('╚══════════════════════════════════════╝');
        $output->writeln('');
        $output->writeln("  数据表:  <info>{$tableName}</info>");
        $output->writeln("  模块名:  <info>{$moduleName}</info>");
        $output->writeln("  模型名:  <info>{$modelName}</info>");
        $output->writeln("  说  明:  <info>{$comment}</info>");
        $output->writeln("  字段数:  <info>" . count($columns) . "</info>");
        $output->writeln('');

        $config = [
            'table_name'    => $tableName,
            'module_name'   => $moduleName,
            'model_name'    => $modelName,
            'table_comment' => $comment,
            'columns'       => $columns,
        ];

        $files = $service->generate($config);

        if ($preview) {
            $output->info('预览模式 — 以下文件将被生成:');
            $output->writeln('');
            foreach ($files as $key => $file) {
                $output->writeln("  [{$key}] {$file['path']}");
            }
            $output->writeln('');
            $output->comment('提示: 去掉 --preview 参数即可执行写入');
            return 0;
        }

        // 写入文件
        if ($force) {
            $results = $this->writeFilesForce($files, $service);
        } else {
            $results = $service->writeFiles($files);
        }

        $output->info('生成结果:');
        $output->writeln('');

        $createdCount = 0;
        $skippedCount = 0;
        $appendedCount = 0;
        foreach ($results as $result) {
            $status = $result['status'];
            $path   = $result['path'];
            $reason = $result['reason'] ?? '';

            switch ($status) {
                case 'created':
                    $output->writeln("  <info>✓ 创建</info>  {$path}");
                    $createdCount++;
                    break;
                case 'appended':
                    $output->writeln("  <info>✓ 追加</info>  {$path}");
                    $appendedCount++;
                    break;
                case 'overwritten':
                    $output->writeln("  <comment>✓ 覆盖</comment>  {$path}");
                    $createdCount++;
                    break;
                case 'skipped':
                    $output->writeln("  <comment>- 跳过</comment>  {$path}" . ($reason ? " ({$reason})" : ''));
                    $skippedCount++;
                    break;
            }
        }

        $output->writeln('');
        $output->writeln("  创建: <info>{$createdCount}</info>  追加: <info>{$appendedCount}</info>  跳过: <comment>{$skippedCount}</comment>");
        $output->writeln('');

        if ($skippedCount > 0 && !$force) {
            $output->comment('提示: 使用 --force 参数可覆盖已有文件');
        }

        $output->info('生成完毕！请检查以下事项:');
        $output->writeln('  1. 确认路由文件是否正确追加');
        $output->writeln('  2. 在后台菜单中添加对应的菜单项');
        $output->writeln('  3. 配置对应的权限标识');
        $output->writeln('');

        return 0;
    }

    /**
     * 强制覆盖模式写入
     */
    protected function writeFilesForce(array $files, CodeGeneratorService $service): array
    {
        $written = [];
        $basePath = root_path();

        foreach ($files as $key => $file) {
            if ($key === 'route') {
                // 路由依然走追加逻辑（通过 writeFiles 处理）
                $routeResults = $service->writeFiles(['route' => $file]);
                $written = array_merge($written, $routeResults);
                continue;
            }

            $fullPath = $basePath . $file['path'];
            if (str_starts_with($file['path'], 'admin/')) {
                $fullPath = dirname($basePath) . '/' . $file['path'];
            }

            $dir = dirname($fullPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $existed = file_exists($fullPath);
            file_put_contents($fullPath, $file['content']);
            $written[] = [
                'path'   => $file['path'],
                'status' => $existed ? 'overwritten' : 'created',
            ];
        }

        return $written;
    }

    /**
     * 表名转模型名
     * 如 yd_articles -> Article, user_addresses -> UserAddress
     */
    protected function tableToModel(string $tableName): string
    {
        // 移除常见前缀
        $name = preg_replace('/^[a-z]+_/', '', $tableName);
        // 移除复数s
        $name = rtrim($name, 's');
        // 下划线转大驼峰
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
    }

    /**
     * 获取表注释
     */
    protected function getTableComment(string $tableName): string
    {
        try {
            $tables = \think\facade\Db::query("SHOW TABLE STATUS LIKE '{$tableName}'");
            return $tables[0]['Comment'] ?? '';
        } catch (\Exception $e) {
            return '';
        }
    }
}
