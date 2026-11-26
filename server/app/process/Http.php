<?php

declare(strict_types=1);

namespace app\process;

use core\plugin\WorkerFingerprintRegistry;
use Webman\App;

/**
 * onWorkerStop() 由 webman 的 worker_bind() 绑成 Workerman 回调。
 * 插件 reload（SIGUSR1）停 worker 时会走到这里，删掉本进程的指纹文件。
 * 被 SIGKILL 时来不及回调，靠 liveWorkers() 按死 PID 清理。
 */
class Http extends App
{
    public function onWorkerStop(): void
    {
        WorkerFingerprintRegistry::forget(getmypid());
    }
}
