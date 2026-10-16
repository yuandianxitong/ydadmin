<?php

declare(strict_types=1);

namespace app\command;

use app\service\operations\OutboxService;
use support\Container;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('outbox:publish', '投递一批待处理或租约过期的可靠任务')]
final class OutboxPublishCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln((string) Container::get(OutboxService::class)->publish());

        return self::SUCCESS;
    }
}
