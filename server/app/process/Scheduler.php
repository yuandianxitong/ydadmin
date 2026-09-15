<?php

declare(strict_types=1);

namespace app\process;

use app\service\system\CronScheduleService;
use support\Container;
use support\Log;
use Workerman\Timer;
use Workerman\Worker;

/**
 * 定时任务调度进程（spec §5、§8.1），config/process.php 注册为 count=1。
 *
 * 只管时间：1 秒定时器发现分钟变了，就把判定交给 CronScheduleService::tick()。
 * 分钟游标是进程**实例**属性（不是静态属性，check:context 规则一无需登记）；reload 后游标归零，
 * 只算当前分钟，已处理过的分钟由 Redis 触发锁去重。
 * Timer 回调里的未捕获异常会让进程退出，所以 tickIfMinuteChanged() 吞掉全部 \Throwable 并记日志；
 * 失败的这一分钟不每秒重试，游标照样前移，下一分钟重试。
 */
class Scheduler
{
    private ?\DateTimeImmutable $lastMinute = null;

    /**
     * $service 仅供测试直接 new Scheduler($service) 注入。生产环境下 webman 按 config/process.php 的
     * 'constructor' 配置构造进程类——本进程没有配置该键，所以这里恒为 null，首次 tick 时从容器取
     * CronScheduleService（容器单例，和显式注入拿到的是同一个实例）。
     */
    public function __construct(private readonly ?CronScheduleService $service = null)
    {
    }

    public function onWorkerStart(Worker $worker): void
    {
        Timer::add(1, function (): void {
            $this->tickIfMinuteChanged(new \DateTimeImmutable('now'));
        });
    }

    /** @return bool 这一次是否调用了 tick() 且成功 */
    public function tickIfMinuteChanged(\DateTimeImmutable $now): bool
    {
        $minute = $now->setTime((int) $now->format('H'), (int) $now->format('i'));
        if ($this->lastMinute !== null && $this->lastMinute->format('YmdHi') === $minute->format('YmdHi')) {
            return false;
        }

        try {
            $this->lastMinute = ($this->service ?? Container::get(CronScheduleService::class))->tick($now, $this->lastMinute);

            return true;
        } catch (\Throwable $e) {
            $this->lastMinute = $minute;
            Log::error('cron.scheduler.tick_failed', [
                'minute' => $minute->format('Y-m-d H:i'),
                'error'  => mb_substr($e->getMessage(), 0, 500),
            ]);

            return false;
        }
    }
}
