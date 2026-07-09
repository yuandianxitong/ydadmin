<?php
declare(strict_types=1);

namespace app\command;

use app\service\system\CodeGeneratorService;
use core\ai\YdConfig;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class YdAiDoctorCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('yd:ai:doctor')->setDescription('检查 AI 生成功能的本地环境');
    }

    protected function execute(Input $input, Output $output): int
    {
        $endpoint = (string) config('ai.endpoint');
        $allOk = true;
        $check = function (string $name, bool $ok, string $hint = '') use ($output, &$allOk) {
            $output->writeln(($ok ? '<info>  ✓ </info>' : '<error>  ✗ </error>') . $name . ($ok || $hint === '' ? '' : "\n      → {$hint}"));
            $allOk = $allOk && $ok;
        };

        $output->writeln('元点 AI 环境体检：');
        $check('PHP ' . PHP_VERSION . '（需 ≥8.0）且 curl 扩展', PHP_VERSION_ID >= 80000 && extension_loaded('curl'), '升级 PHP 或安装 curl 扩展');

        exec('git --version 2>/dev/null', $o, $gitCode);
        $check('git 可用（diff 预览依赖）', $gitCode === 0, '安装 git');

        try {
            (new CodeGeneratorService())->getTableColumns('menus');
            $check('数据库连通', true);
        } catch (\Throwable $e) {
            $check('数据库连通', false, '检查 server/.env 数据库配置：' . $e->getMessage());
        }

        $ch = curl_init($endpoint . '/api/v1/health');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5, CURLOPT_NOPROXY => '127.0.0.1,localhost']);
        curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $source = env('YD_AI_ENDPOINT') ? '.env YD_AI_ENDPOINT' : '默认配置';
        $check("AI 引擎可达（{$endpoint}，来源：{$source}）", $status === 200, '确认引擎已启动或修改 YD_AI_ENDPOINT');

        $config = new YdConfig();
        $token = env('YD_AI_TOKEN') ?: $config->get('token');
        $check('Token 状态：' . ($token ? '已配置' : '未配置（本地 dev 模式可用）'), true);

        $check('runtime 目录可写', is_writable(runtime_path()), 'chmod 可写权限');

        $output->writeln($allOk ? '<info>全部通过，可以使用 php think yd:ai</info>' : '<error>存在问题，请按提示修复</error>');
        return $allOk ? 0 : 1;
    }
}
