<?php

declare(strict_types=1);

namespace app\process;

use app\model\plugin\PluginSaga;
use app\repository\plugin\PluginSagaRepository;
use app\service\plugin\PluginService;
use support\Container;
use support\Log;
use Workerman\Timer;
use Workerman\Worker;

/**
 * 插件生效派单进程。reloadable 必须是 false：它要在自己触发的重载里活下来，
 * 才能看着 worker 指纹收敛。HTTP 请求只负责把 saga 写成 pending。
 */
class PluginSagaDispatcher
{
    private bool $busy = false;

    private PluginSagaRepository $repo;

    public function onWorkerStart(Worker $worker): void
    {
        $this->repo = Container::get(PluginSagaRepository::class);

        Timer::add(1, function (): void {
            try {
                $this->recoverOrphans();
            } catch (\Throwable $e) {
                Log::error('[plugin-saga] 崩溃恢复扫描失败：' . $e->getMessage());
            }
        }, [], false);

        Timer::add(1, function (): void {
            try {
                $this->tick();
            } catch (\Throwable $e) {
                Log::error('[plugin-saga] 派单失败：' . $e->getMessage());
            }
        });
    }

    public function recoverOrphans(): int
    {
        $myPid = (int) getmypid();
        $n = 0;
        foreach ($this->repo->findRunning() as $saga) {
            $pid = (int) ($saga['claimed_pid'] ?? 0);
            if ($pid > 0 && @posix_kill($pid, 0)) {
                continue;
            }
            if (!$this->repo->reclaimOrphan((int) $saga['id'], $pid, $myPid)) {
                continue;
            }
            Log::warning('[plugin-saga] 认领进程已死，转入只补偿恢复：saga=' . $saga['id']);
            $this->runViaSubprocess((int) $saga['id'], true);
            $n++;
        }

        return $n;
    }

    public function tick(): void
    {
        if ($this->busy) {
            return;
        }
        $saga = $this->repo->claimNext((int) getmypid());
        if ($saga === null) {
            return;
        }
        $this->busy = true;
        try {
            $this->runViaSubprocess((int) $saga['id'], false);
        } finally {
            $this->busy = false;
        }
    }

    private function runViaSubprocess(int $sagaId, bool $compensateOnly, bool $escalated = false): void
    {
        $cmd = sprintf(
            'YDADMIN_PLUGIN_BOOT=0 %s %s plugin:saga:run %d%s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(base_path('webman')),
            $sagaId,
            $compensateOnly ? ' --compensate-only' : ''
        );
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $pipes = [];
        $process = @proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($process)) {
            Log::error("[plugin-saga] saga #{$sagaId} 派发失败：proc_open() 未能启动子进程");
            $this->ensureSagaSettled($sagaId, 'proc_open() 未能启动子进程', $escalated);

            return;
        }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stderr = '';
        $deadline = microtime(true) + 180;
        $status = proc_get_status($process);
        while ($status['running'] && microtime(true) < $deadline) {
            $stderr .= (string) stream_get_contents($pipes[2]);
            stream_get_contents($pipes[1]);
            usleep(200_000);
            $status = proc_get_status($process);
        }
        if ($status['running']) {
            proc_terminate($process);
            usleep(200_000);
            $status = proc_get_status($process);
            if ($status['running']) {
                proc_terminate($process, SIGKILL);
            }
        }
        $stderr .= (string) stream_get_contents($pipes[2]);
        stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            Log::error(sprintf(
                '[plugin-saga] saga #%d 子进程退出码=%d，stderr=%s',
                $sagaId,
                $exitCode,
                mb_substr($stderr, 0, 500)
            ));
        }
        $this->ensureSagaSettled($sagaId, sprintf('子进程退出码=%d', $exitCode), $escalated);
    }

    private function ensureSagaSettled(int $sagaId, string $reason, bool $escalated): void
    {
        $saga = $this->repo->find($sagaId);
        if ($saga === null) {
            return;
        }
        $status = (string) ($saga['status'] ?? '');
        if (in_array($status, [PluginSaga::ST_COMPLETED, PluginSaga::ST_FAILED], true)) {
            return;
        }
        if (!$escalated) {
            Log::warning(sprintf(
                '[plugin-saga] saga #%d 仍停在 %s（%s），转入只补偿收尾',
                $sagaId,
                $status,
                $reason
            ));
            $this->runViaSubprocess($sagaId, true, true);

            return;
        }

        Log::error(sprintf('[plugin-saga] saga #%d 只补偿后仍未收尾（%s）', $sagaId, $reason));
        $this->repo->finish($sagaId, PluginSaga::ST_FAILED, '编排者侧收尾：' . $reason);
        Container::get(PluginService::class)->releaseStuck(
            (int) $saga['plugin_id'],
            (string) $saga['operation'],
            $reason
        );
    }
}
