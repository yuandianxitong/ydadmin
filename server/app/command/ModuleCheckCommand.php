<?php

declare(strict_types=1);

namespace app\command;

use app\service\system\ModuleService;
use support\Container;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('module:check', '检查 JSON 模块描述、权限引用和产物冲突')]
final class ModuleCheckCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('files', null, InputOption::VALUE_NONE, '同时检查前后端产物是否存在（完整开发仓库使用）');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = Container::get(ModuleService::class)->inspect((bool) $input->getOption('files'));
        $output->writeln(json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), OutputInterface::OUTPUT_RAW);
        return $result['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
