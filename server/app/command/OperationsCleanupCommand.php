<?php

declare(strict_types=1);

namespace app\command;

use app\service\operations\MaintenanceService;
use support\Container;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('operations:cleanup', '有界清理过期任务文件、投递记录和幂等结果')]
final class OperationsCleanupCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(json_encode(Container::get(MaintenanceService::class)->cleanup(), JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
