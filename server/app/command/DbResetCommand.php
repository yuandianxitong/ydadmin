<?php

declare(strict_types=1);

namespace app\command;

use core\database\DatabaseInstaller;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand('db:reset', '删库重建开发数据库并导入 schema.sql、init.sql 与 regions.sql（仅 APP_DEBUG=true 可用）')]
final class DbResetCommand extends Command
{
    /**
     * @param bool|null   $debug      测试注入用；null 时读 config('app.debug')
     * @param string|null $lockPath   测试注入用；null 时写 runtime/install.lock
     * @param string|null $installDir 测试注入迷你 SQL；null 时用 database/install
     * @param string|null $database   测试注入隔离库；null 时用当前 config 库名
     */
    public function __construct(
        private readonly ?bool $debug = null,
        private readonly ?string $lockPath = null,
        private readonly ?string $installDir = null,
        private readonly ?string $database = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, '跳过交互确认');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!($this->debug ?? (bool) config('app.debug'))) {
            $output->writeln('<error>db:reset 只能在开发环境使用（APP_DEBUG=true）</error>');

            return self::FAILURE;
        }

        $connection = (array) config('database.connections.mysql');
        $database = $this->database ?? (string) ($connection['database'] ?? '');
        if (!$input->getOption('force')) {
            $question = new ConfirmationQuestion("将删除并重建数据库 {$database}，其中数据全部丢失。继续？[y/N] ", false);
            if (!(new QuestionHelper())->ask($input, $output, $question)) {
                $output->writeln('已取消');

                return self::FAILURE;
            }
        }

        $pdo = DatabaseInstaller::connect($connection);
        DatabaseInstaller::reinstall($pdo, $database, $this->installDir ?? base_path() . '/database/install');
        $pdo->exec("USE `{$database}`");
        $pdo->exec("INSERT IGNORE INTO system_upgrades (version, applied_at) VALUES ('2.0.0', NOW())");
        file_put_contents($this->lockPath ?? runtime_path() . '/install.lock', date('c'));
        $output->writeln("<info>数据库 {$database} 已重建。lock 已写，超管用 admin:init 或安装向导</info>");

        return self::SUCCESS;
    }
}
