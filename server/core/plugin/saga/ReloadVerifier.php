<?php

declare(strict_types=1);

namespace core\plugin\saga;

use core\exception\BusinessException;
use core\plugin\contracts\PluginHostProfile;
use core\plugin\WorkerFingerprintRegistry;
use support\Log;

/**
 * 滚动 reload 生效校验：轮询 worker 指纹注册表，直到存活集合非空且每个指纹都等于期望值；超时抛异常。
 *
 * - 被 SIGKILL 的 worker 留下的孤儿 pid 可能被无关进程复用，陈旧指纹会永久拖住判定。所以每轮用
 *   master 的子进程集合再过滤一次；枚举失败时退化为不过滤，每次调用最多记一次 warning。
 * - 空集合（过滤前或过滤后）不算生效，否则 worker 全挂时会误报成功。
 *
 * 不是 final：测试需要覆写 resolveMasterChildPids() 固定子进程集合。
 */
class ReloadVerifier
{
    public function __construct(private readonly PluginHostProfile $profile)
    {
    }

    /**
     * @throws BusinessException 超时仍未收敛到 $expected
     */
    public function waitUntilEffective(string $expected, ?int $timeoutSeconds = null): string
    {
        $timeout = $timeoutSeconds ?? $this->profile->reloadTimeoutSeconds();
        $intervalMs = max(1, $this->profile->reloadPollIntervalMs());
        $deadline = microtime(true) + $timeout;
        $degradeLogged = false;
        /** @var array<int, array{pid:int,fingerprint:string,booted_at:string}> $filtered */
        $filtered = [];

        do {
            $live = WorkerFingerprintRegistry::liveWorkers();
            $childPids = $this->resolveMasterChildPids();

            if ($childPids === null) {
                if (!$degradeLogged) {
                    Log::warning('[plugin-reload] 无法枚举 master 子进程集合，本次生效校验退化为仅按 posix_kill 存活判定（陈旧指纹若被复用 pid 持有，将不再被过滤）');
                    $degradeLogged = true;
                }
                $filtered = $live;
            } else {
                $filtered = array_values(array_filter(
                    $live,
                    static fn (array $w): bool => in_array($w['pid'], $childPids, true)
                ));
            }

            if ($filtered !== []) {
                $mismatched = array_values(array_filter(
                    $filtered,
                    static fn (array $w): bool => $w['fingerprint'] !== $expected
                ));
                if ($mismatched === []) {
                    return $expected;
                }
            }

            usleep($intervalMs * 1000);
        } while (microtime(true) < $deadline);

        if ($filtered === []) {
            throw new BusinessException(sprintf(
                'reload 未在 %ds 内生效：没有任何存活 worker 报到（注册表为空，或全部条目被 master 子进程集合过滤排除）',
                $timeout
            ));
        }

        $mismatched = array_values(array_filter(
            $filtered,
            static fn (array $w): bool => $w['fingerprint'] !== $expected
        ));
        $detail = implode('; ', array_map(
            static fn (array $w): string => "pid={$w['pid']} fingerprint={$w['fingerprint']}",
            $mismatched
        ));

        throw new BusinessException(sprintf(
            'reload 未在 %ds 内生效：仍有存活 worker 报告与期望不一致的指纹（期望=%s；%s）',
            $timeout,
            $expected,
            $detail
        ));
    }

    /**
     * 枚举 master 的子进程 pid 集合。
     *
     * @return array<int, int>|null null 表示枚举失败（master 未运行/pid 文件不可读/
     *                               `pgrep` 不可用等），调用方应退化为不过滤。
     */
    protected function resolveMasterChildPids(): ?array
    {
        $file = (string) config('server.pid_file');
        if (!is_file($file)) {
            return null;
        }

        $content = @file_get_contents($file);
        $masterPid = $content === false ? 0 : (int) trim($content);
        if ($masterPid <= 0 || !posix_kill($masterPid, 0)) {
            return null;
        }

        // macOS/Linux 均自带 pgrep；用数组形式 proc_open（不经 shell），避免拼接命令行的转义风险。
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = @proc_open(['pgrep', '-P', (string) $masterPid], $descriptors, $pipes);
        if (!is_resource($process)) {
            return null;
        }

        $stdout = stream_get_contents($pipes[1]);
        $stdout = $stdout === false ? '' : $stdout;
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        // pgrep 退出码：0=有匹配，1=无匹配（合法结果，master 当前没有子进程，不是枚举失败），
        // 其余（含 2=参数错误、127=命令不存在经 shell 时的约定、-1=proc_close 异常）一律视为枚举失败。
        if ($exitCode === 1) {
            return [];
        }
        if ($exitCode !== 0) {
            return null;
        }

        $pids = [];
        foreach (preg_split('/\s+/', trim($stdout)) ?: [] as $token) {
            if ($token !== '' && ctype_digit($token)) {
                $pids[] = (int) $token;
            }
        }

        return $pids;
    }
}
