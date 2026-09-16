<?php

declare(strict_types=1);

namespace tests\Support;

use support\Db;

/**
 * 真实并发测试的通用骨架（从 tests/Feature/User/AssetConcurrencyTest.php 搬来，理由原样保留）。
 *
 * 为什么是 pcntl_fork，而不是「一个进程里开两个连接交错执行」：
 *   被测代码在 runInTransaction 里 lockForUpdate，行锁一直持有到提交。
 *   单进程里第二个连接一撞上这把锁就阻塞，而阻塞的是唯一的 PHP 线程——测试自己先死锁，
 *   永远走不到「两边都提交」。fork 出来的子进程各跑一遍完整的被测方法，各自持有自己的 PDO
 *   连接，是操作系统层面的真并发，也是线上真正会发生的形态（webman 多 worker 进程）。
 *
 * 三条安全做法，缺一不可：
 *   1. fork 之前父进程 Db::connection()->disconnect()。PDO 连接不能被多个进程共用：共用会把两个
 *      进程的查询和结果包交错写进同一个 socket。断开后，每个子进程首次查询时各自新建连接，
 *      父进程在收完子进程后同样会惰性重连。
 *   2. 子进程干完活立刻 SIGKILL 自己。exit() 会触发 PHPUnit 注册的 shutdown 回调，子进程会跟着
 *      再打印一遍测试结果。结果先 file_put_contents 落盘（同步写），再自杀。
 *   3. 子进程内不做断言。断言计数留在子进程里父进程看不到，断言一律在父进程按落盘的结果做。
 *
 * 并发的「同时」靠墙钟对齐：父进程定一个 0.5 秒后的起跑时刻，所有子进程忙等到那一刻再开跑。
 * 但对齐只让重叠「几乎必然」，本身不是保证：CI 上子进程启动被拉开、或调度把两者串行化时，
 * 一个把 findForUpdate() 换成普通读的坏实现也可能蒙混过绿（只会假绿、不会假红，但假绿更危险——
 * M5b 的支付回调押的就是这条不变量）。所以每个子进程另外写回自己干活那段的首末时间戳，
 * 父进程断言各区间两两相交（assertWorkersOverlapped()）：不相交就说明这一跑压根没并发上，必须红。
 *
 * 使用方在 tearDown 里调 cleanupConcurrencyFiles()。
 */
trait ConcurrentWorkers
{
    private function concurrencyDir(): string
    {
        return base_path() . '/runtime/tests-concurrency';
    }

    /**
     * 兜底清掉结果文件与目录：runConcurrently() 里的 unlink() 排在断言之后，某个子进程缺文件时
     * 后面几个的结果文件会留下；目录本身也只建不删。/runtime/ 虽已 gitignore，但不留垃圾。
     */
    private function cleanupConcurrencyFiles(): void
    {
        $dir = $this->concurrencyDir();
        if (is_dir($dir)) {
            foreach (glob($dir . '/*.json') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
    }

    /**
     * 并发跑 $workers 个子进程，每个执行 $worker($index)，返回值（JSON 可序列化）落盘后由父进程收回。
     *
     * @param \Closure(int): array<string, mixed> $worker
     * @return list<array<string, mixed>>
     */
    private function runConcurrently(int $workers, \Closure $worker): array
    {
        $dir = $this->concurrencyDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }
        $files = [];
        for ($i = 0; $i < $workers; $i++) {
            $files[$i] = $dir . '/' . bin2hex(random_bytes(8)) . '.json';
        }

        // 1. 断开父进程的 PDO 连接，fork 之后父子才不会共用同一个 socket
        Db::connection()->disconnect();
        $startAt = microtime(true) + 0.5;

        $pids = [];
        for ($i = 0; $i < $workers; $i++) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                $this->fail('pcntl_fork 失败');
            }
            if ($pid === 0) {
                // ---- 子进程
                try {
                    while (microtime(true) < $startAt) {  // 2. 等到同一个墙钟时刻再开跑
                        usleep(1000);
                    }
                    // 首末时间戳只夹住真正干活的那段（忙等不算）：父进程靠它断言重叠是真的发生了。
                    // 用 hrtime() 而不是 microtime()：它是全系统单调时钟，同一台机上跨进程可比，
                    // 且不受 NTP 回拨影响——墙钟回拨会让区间比较得出假结论。
                    $startedAt = hrtime(true);
                    $result = $worker($i);
                    $result['started_at'] = $startedAt;
                    $result['ended_at'] = hrtime(true);
                } catch (\Throwable $e) {
                    $result = ['fatal' => $e::class . ': ' . $e->getMessage()];
                }
                file_put_contents($files[$i], (string) json_encode($result));
                posix_kill(posix_getpid(), SIGKILL);  // 3. 不让 PHPUnit 的 shutdown 回调在子进程里再跑一遍
            }
            $pids[] = $pid;
        }

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        $results = [];
        foreach ($files as $i => $file) {
            $this->assertFileExists($file, "子进程 {$i} 没有写回结果");
            $decoded = (array) json_decode((string) file_get_contents($file), true);
            unlink($file);
            $this->assertArrayNotHasKey('fatal', $decoded, '子进程异常：' . (string) ($decoded['fatal'] ?? ''));
            $results[] = $decoded;
        }

        return $results;
    }

    /**
     * 断言所有子进程「干活」的时间区间两两相交——即这一跑真的并发上了。
     *
     * N 个区间存在公共交集的充要条件是 max(起) < min(止)；两个 worker 时就是区间相交。
     * 这条断言必须能真判别：区间不相交时 max(起) >= min(止)，assertLessThan 直接红。
     * 它红的含义不是「实现错了」，而是「这一跑没并发上，本用例这次什么也没验证到」——
     * 同样不能放过，否则并发正确性就只剩一个没人守的假设。
     *
     * @param list<array<string, mixed>> $results
     */
    private function assertWorkersOverlapped(array $results): void
    {
        $starts = array_map(intval(...), array_column($results, 'started_at'));
        $ends = array_map(intval(...), array_column($results, 'ended_at'));
        $this->assertCount(count($results), $starts, '每个子进程都要写回 started_at');
        $this->assertCount(count($results), $ends, '每个子进程都要写回 ended_at');

        $latestStart = max($starts);
        $earliestEnd = min($ends);
        $overlapMs = ($earliestEnd - $latestStart) / 1_000_000;
        $spans = [];
        foreach ($results as $i => $_) {
            $spans[] = sprintf('w%d 持续 %.1fms', $i, ($ends[$i] - $starts[$i]) / 1_000_000);
        }

        $this->assertLessThan(
            $earliestEnd,
            $latestStart,
            sprintf(
                '两个子进程的执行区间没有重叠（重叠 %.1fms，%s）：这一跑是串行的，并发正确性没有被验证到。'
                . '不是实现错了，但也不能放过——串行跑下去，一个去掉行锁的实现同样会绿。',
                $overlapMs,
                implode('，', $spans),
            ),
        );
    }
}
