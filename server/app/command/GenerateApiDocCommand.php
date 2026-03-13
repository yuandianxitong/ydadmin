<?php
declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Option;

/**
 * API 文档生成命令
 *
 * 用法:
 *   php think api:doc                    # 生成到 public/openapi.json
 *   php think api:doc -o docs/api.json   # 自定义输出路径
 *   php think api:doc --yaml             # 输出 YAML 格式
 */
class GenerateApiDocCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('api:doc')
            ->setDescription('从 OpenAPI 注解生成 API 文档')
            ->addOption('output', 'o', Option::VALUE_REQUIRED, '输出文件路径', 'public/openapi.json')
            ->addOption('yaml', null, Option::VALUE_NONE, '输出 YAML 格式（默认 JSON）');
    }

    protected function execute(Input $input, Output $output): int
    {
        $outputPath = $input->getOption('output');
        $isYaml = $input->getOption('yaml');

        if ($isYaml && str_ends_with($outputPath, '.json')) {
            $outputPath = str_replace('.json', '.yaml', $outputPath);
        }

        $output->writeln('<info>正在扫描 OpenAPI 注解...</info>');

        $scanPaths = [
            app_path() . 'controller/',
            root_path() . 'core/base/Controller.php',
        ];

        // 抑制 deprecation warning
        $previousLevel = error_reporting(E_ALL & ~E_DEPRECATED);
        ob_start();

        try {
            $openapi = \OpenApi\Generator::scan($scanPaths);

            if ($isYaml) {
                $content = $openapi->toYaml();
            } else {
                $content = $openapi->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            }

            ob_end_clean();
            error_reporting($previousLevel);
        } catch (\Exception $e) {
            ob_end_clean();
            error_reporting($previousLevel);
            $output->writeln('<error>生成失败: ' . $e->getMessage() . '</error>');
            return 1;
        }

        // 确保目录存在
        $dir = dirname(root_path() . $outputPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fullPath = root_path() . $outputPath;
        file_put_contents($fullPath, $content);

        $format = $isYaml ? 'YAML' : 'JSON';
        $output->writeln("<info>API 文档已生成 ({$format}): {$fullPath}</info>");

        // 统计信息
        if (!$isYaml) {
            $data = json_decode($content, true);
            $pathCount = count($data['paths'] ?? []);
            $tagCount = count($data['tags'] ?? []);
            $output->writeln("<comment>  - 接口路径: {$pathCount} 个</comment>");
            $output->writeln("<comment>  - 标签分组: {$tagCount} 个</comment>");
        }

        return 0;
    }
}
