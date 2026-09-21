<?php

declare(strict_types=1);

namespace app\command;

use core\database\DatabaseInstaller;
use core\exception\BusinessException;
use core\exception\ValidationException;
use core\install\Upgrader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** 命令只做参数解析与输出，逻辑在 Upgrader::run()。 */
#[AsCommand('yd:update', '执行框架数据库增量升级（database/updates/vX.Y.Z）')]
final class YdUpdateCommand extends Command
{
    /**
     * @param object|null $upgrader 测试可注入带 run() 的替身；null 则 new Upgrader(base_path().'/database/updates')
     * @param \PDO|null   $pdo      测试可注入；null 则 DatabaseInstaller::connect + USE 当前库
     */
    public function __construct(
        private readonly ?object $upgrader = null,
        private readonly ?\PDO $pdo = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, '仅列出将打标 / 将执行的版本，不做任何写操作')
            ->addOption('baseline', null, InputOption::VALUE_REQUIRED, '首次使用时确立基线（已应用为空时必填）');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>框架代码版本：' . (string) config('version.version') . '</info>');

        $dryRun = (bool) $input->getOption('dry-run');
        $rawBaseline = $input->getOption('baseline');
        $baseline = $rawBaseline === null || $rawBaseline === '' ? null : (string) $rawBaseline;

        try {
            $result = $this->runUpgrade($this->pdo ?? $this->connect(), $baseline, $dryRun);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $message) {
                $output->writeln("<error>{$field}：{$message}</error>");
            }

            return self::FAILURE;
        } catch (BusinessException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return self::FAILURE;
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return self::FAILURE;
        }

        if ($result['stamped'] !== []) {
            $output->writeln('<info>打标：' . implode(', ', $result['stamped']) . '</info>');
        }
        if ($result['executed'] !== []) {
            $output->writeln('<info>已执行：' . implode(', ', $result['executed']) . '</info>');
        }
        if ($result['pending'] !== []) {
            $output->writeln('<info>待执行：' . implode(', ', $result['pending']) . '</info>');
        }
        if ($dryRun) {
            $output->writeln('<comment>dry-run 模式，未执行任何写入。</comment>');
        } elseif ($result['stamped'] === [] && $result['executed'] === []) {
            $output->writeln('<info>数据库已是最新，无需升级。</info>');
        }

        return self::SUCCESS;
    }

    /**
     * @return array{stamped: list<string>, executed: list<string>, pending: list<string>}
     */
    private function runUpgrade(\PDO $pdo, ?string $baseline, bool $dryRun): array
    {
        $upgrader = $this->upgrader ?? new Upgrader(base_path() . '/database/updates');
        if ($upgrader instanceof Upgrader) {
            return $upgrader->run($pdo, $baseline, $dryRun);
        }
        $run = [$upgrader, 'run'];
        if (!is_callable($run)) {
            throw new \LogicException('upgrader 必须提供 run()');
        }

        /** @var array{stamped: list<string>, executed: list<string>, pending: list<string>} $result */
        $result = $run($pdo, $baseline, $dryRun);

        return $result;
    }

    private function connect(): \PDO
    {
        $connection = (array) config('database.connections.mysql');
        $pdo = DatabaseInstaller::connect($connection);
        $database = (string) ($connection['database'] ?? '');
        if (preg_match('/^[A-Za-z0-9_]+$/', $database) !== 1) {
            throw new BusinessException('数据库名只允许字母、数字与下划线');
        }
        $pdo->exec("USE `{$database}`");

        return $pdo;
    }
}
