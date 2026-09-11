<?php

declare(strict_types=1);

namespace app\command;

use app\service\system\AdminService;
use core\exception\BusinessException;
use core\exception\ValidationException;
use support\Container;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** 命令只做参数解析与输出，逻辑在 AdminService::initSuperAdmin()（M8 安装向导复用）。 */
#[AsCommand('admin:init', '建立或重置 id=1 的超级管理员（该账号已签发的 token 全部失效）')]
final class AdminInitCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('username', null, InputOption::VALUE_REQUIRED, '用户名（3-20 位字母、数字、_ 或 -）')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, '密码（最短长度受系统配置 password_min_length 约束，最长 20）')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, '邮箱（可选）')
            ->addOption('nickname', null, InputOption::VALUE_REQUIRED, '昵称（可选，新建时默认同用户名）');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $username = (string) $input->getOption('username');
        $password = (string) $input->getOption('password');
        if ($username === '' || $password === '') {
            $output->writeln('<error>--username 与 --password 必填</error>');

            return self::FAILURE;
        }
        $email = (string) $input->getOption('email');
        $nickname = (string) $input->getOption('nickname');

        try {
            $result = Container::get(AdminService::class)->initSuperAdmin(
                $username,
                $password,
                $email !== '' ? $email : null,
                $nickname !== '' ? $nickname : null
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $message) {
                $output->writeln("<error>{$field}：{$message}</error>");
            }

            return self::FAILURE;
        } catch (BusinessException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return self::FAILURE;
        }

        $output->writeln(sprintf(
            '<info>超级管理员已%s：id=%d，用户名 %s。该账号此前签发的 token 已全部失效。</info>',
            $result['created'] ? '创建' : '重置',
            $result['id'],
            $result['username']
        ));

        return self::SUCCESS;
    }
}
