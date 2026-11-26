<?php

declare(strict_types=1);

namespace app\command;

use app\service\plugin\PluginService;
use core\plugin\saga\PluginSagaRunner;
use support\Container;
use support\Log;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * 一次性进程，跑完即死。由不参与重载的派单进程拉起，这样发 SIGUSR1 的时候
 * HTTP worker 已经把响应送走，不会被 stop_timeout 杀掉。
 */
#[AsCommand('plugin:saga:run', '执行一条 plugin_sagas（一次性进程，跑完即死）')]
final class PluginSagaRunCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('sagaId', InputArgument::REQUIRED, 'plugin_sagas.id')
            ->addOption('compensate-only', null, InputOption::VALUE_NONE, '只补偿已完成步骤，不续跑');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sagaId = (int) $input->getArgument('sagaId');
        $compensateOnly = (bool) $input->getOption('compensate-only');
        Log::info(sprintf('[plugin-saga] 子进程启动，开始执行 saga #%d', $sagaId));

        $exit = Container::get(PluginSagaRunner::class)->handle($sagaId, $compensateOnly);
        if ($exit === 0 && !$compensateOnly) {
            Container::get(PluginService::class)->finalizeSaga($sagaId);
        }

        return $exit;
    }
}
