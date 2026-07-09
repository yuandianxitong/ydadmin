<?php
// server/app/command/YdAiLoginCommand.php
declare(strict_types=1);

namespace app\command;

use core\ai\YdConfig;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

class YdAiLoginCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('yd:login')
            ->setDescription('配置元点 AI 服务的访问 Token')
            ->addOption('show', null, Option::VALUE_NONE, '显示当前 Token 状态')
            ->addOption('clear', null, Option::VALUE_NONE, '清除全局存储的 Token');
    }

    protected function execute(Input $input, Output $output): int
    {
        $config = new YdConfig();

        if ($input->getOption('show')) {
            $envToken = env('YD_AI_TOKEN');
            $cfgToken = $config->get('token');
            if ($envToken) {
                $output->info('Token 来源：项目 .env（YD_AI_TOKEN）  ' . $this->mask((string) $envToken));
            } elseif ($cfgToken) {
                $output->info('Token 来源：' . $config->path() . '  ' . $this->mask((string) $cfgToken));
            } else {
                $output->comment('未配置 Token（本地引擎 dev 模式下可直接使用 yd:ai）');
            }
            return 0;
        }

        if ($input->getOption('clear')) {
            $config->set('token', null);
            $output->info('已清除全局 Token。若 .env 中有 YD_AI_TOKEN 请手工删除。');
            return 0;
        }

        $token = trim((string) $output->ask($input, '请粘贴你的 AI Token（从官网获取）'));
        if ($token === '') {
            $output->error('Token 不能为空');
            return 1;
        }
        $config->set('token', $token);
        $output->info('Token 已保存到 ' . $config->path() . '（权限 0600）');
        return 0;
    }

    protected function mask(string $token): string
    {
        return strlen($token) > 12 ? substr($token, 0, 8) . '…' . substr($token, -4) : '（短 token）';
    }
}
