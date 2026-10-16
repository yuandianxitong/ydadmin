<?php

declare(strict_types=1);

namespace app\command;

use app\service\system\ApiDocService;
use core\apidoc\OpenApiDocument;
use core\apidoc\TypeScriptGenerator;
use support\Container;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Webman\Route;

#[AsCommand('api:export', '从实际路由与契约导出 OpenAPI 和 TypeScript 类型')]
final class ApiExportCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('type', null, InputOption::VALUE_REQUIRED, 'admin 或 api', 'admin');
        $this->addOption('output', null, InputOption::VALUE_REQUIRED, '输出目录，默认 runtime/contracts');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $type = $input->getOption('type');
        if (!in_array($type, ['admin', 'api'], true)) {
            $output->writeln('<error>--type 必须为 admin 或 api</error>');

            return self::FAILURE;
        }
        $directory = $input->getOption('output') ?? runtime_path('contracts');
        if (!is_string($directory) || $directory === '' || (!is_dir($directory) && !mkdir($directory, 0o755, true))) {
            $output->writeln('<error>无法创建输出目录</error>');

            return self::FAILURE;
        }
        if (Route::getRoutes() === []) {
            Route::load([config_path()]);
        }
        $document = Container::get(ApiDocService::class)->document($type);
        $files = [
            'openapi-' . $type . '.json' => OpenApiDocument::toJson($document) . "\n",
            $type . '.d.ts' => (new TypeScriptGenerator())->generate($document),
        ];
        foreach ($files as $name => $content) {
            $path = rtrim($directory, '/') . '/' . $name;
            $temporary = tempnam($directory, '.contract-');
            if ($temporary === false) {
                throw new \RuntimeException('无法创建契约临时文件');
            }
            try {
                if (file_put_contents($temporary, $content) === false || !rename($temporary, $path)) {
                    throw new \RuntimeException('无法写入契约文件');
                }
            } finally {
                if (is_file($temporary)) {
                    unlink($temporary);
                }
            }
            $output->writeln($path);
        }

        return self::SUCCESS;
    }
}
