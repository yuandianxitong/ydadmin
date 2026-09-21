<?php

declare(strict_types=1);

namespace app\command;

use core\exception\BusinessException;
use core\exception\ValidationException;
use core\install\Installer;
use core\validation\ValidatorFactory;
use support\Container;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/** 命令只做参数解析与输出，逻辑在 Installer::run()。 */
#[AsCommand('install', '安装系统（写库、.env、超管与 lock）')]
final class InstallCommand extends Command
{
    /** @var list<string> --no-interaction 时缺一即失败 */
    private const REQUIRED = ['db-name', 'db-user', 'username', 'password'];

    protected function configure(): void
    {
        $this->addOption('db-host', null, InputOption::VALUE_REQUIRED, '数据库主机')
            ->addOption('db-port', null, InputOption::VALUE_REQUIRED, '数据库端口')
            ->addOption('db-name', null, InputOption::VALUE_REQUIRED, '数据库名')
            ->addOption('db-user', null, InputOption::VALUE_REQUIRED, '数据库用户')
            ->addOption('db-password', null, InputOption::VALUE_REQUIRED, '数据库密码')
            ->addOption('redis-host', null, InputOption::VALUE_REQUIRED, 'Redis 主机')
            ->addOption('redis-port', null, InputOption::VALUE_REQUIRED, 'Redis 端口')
            ->addOption('redis-password', null, InputOption::VALUE_REQUIRED, 'Redis 密码')
            ->addOption('redis-db', null, InputOption::VALUE_REQUIRED, 'Redis 库号')
            ->addOption('username', null, InputOption::VALUE_REQUIRED, '超管用户名')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, '超管密码')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, '超管邮箱（可选）')
            ->addOption('nickname', null, InputOption::VALUE_REQUIRED, '超管昵称（可选）');
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $helper = new QuestionHelper();
        foreach (self::REQUIRED as $name) {
            if ((string) $input->getOption($name) !== '') {
                continue;
            }
            $answer = (string) $helper->ask($input, $output, new Question("--{$name}: "));
            $input->setOption($name, $answer);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach (self::REQUIRED as $name) {
            if ((string) $input->getOption($name) === '') {
                $output->writeln('<error>--db-name、--db-user、--username 与 --password 在 --no-interaction 下必填</error>');

                return self::FAILURE;
            }
        }

        $mysql = (array) config('database.connections.mysql');
        $redis = (array) config('redis.default');

        try {
            $payload = [
                'db_host'        => $this->optionOr($input, 'db-host', (string) ($mysql['host'] ?? '127.0.0.1')),
                'db_port'        => $this->intOptionOr($input, 'db-port', (int) ($mysql['port'] ?? 3306)),
                'db_name'        => (string) $input->getOption('db-name'),
                'db_user'        => (string) $input->getOption('db-user'),
                'db_password'    => $this->optionOr($input, 'db-password', (string) ($mysql['password'] ?? '')),
                'redis_host'     => $this->optionOr($input, 'redis-host', (string) ($redis['host'] ?? '127.0.0.1')),
                'redis_port'     => $this->intOptionOr($input, 'redis-port', (int) ($redis['port'] ?? 6379)),
                'redis_password' => $this->optionOr($input, 'redis-password', (string) ($redis['password'] ?? '')),
                'redis_db'       => $this->intOptionOr($input, 'redis-db', (int) ($redis['database'] ?? 0)),
                'username'       => (string) $input->getOption('username'),
                'password'       => (string) $input->getOption('password'),
                'email'          => $this->nullableOption($input, 'email'),
                'nickname'       => $this->nullableOption($input, 'nickname'),
            ];
            ValidatorFactory::validate($payload, [
                'username' => 'required|string|min:3|max:20|alpha_dash:ascii',
                'password' => 'required|string|min:6|max:20',
                'email'    => 'nullable|email|max:100',
                'nickname' => 'nullable|string|max:50',
            ]);
            Container::get(Installer::class)->run($payload);
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

        $output->writeln('<info>' . lang('install.restart_hint') . '</info>');

        return self::SUCCESS;
    }

    private function optionOr(InputInterface $input, string $name, string $default): string
    {
        $value = $input->getOption($name);

        return $value === null || $value === '' ? $default : (string) $value;
    }

    private function intOptionOr(InputInterface $input, string $name, int $default): int
    {
        $value = $input->getOption($name);

        return $value === null || $value === '' ? $default : (int) $value;
    }

    private function nullableOption(InputInterface $input, string $name): ?string
    {
        $value = $input->getOption($name);

        return $value === null || $value === '' ? null : (string) $value;
    }
}
