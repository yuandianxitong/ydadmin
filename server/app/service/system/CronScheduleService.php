<?php

declare(strict_types=1);

namespace app\service\system;

use app\repository\system\CronJobRepository;
use core\base\Service;
use core\cron\CronSchedule;
use core\queue\QueueDispatcher;
use DI\Attribute\Inject;
use support\Log;

/**
 * 定时任务调度判定（spec §8.1）。scheduler 进程每进入新的一分钟调用一次 tick()；本类不依赖事件循环，可直接测。
 *
 * - misfire 策略：只补短暂延迟，不补长时间停机。与上次处理的分钟相差 1..catchup_minutes 分钟时逐分钟补算，
 *   超出则只算当前分钟并记 warning；进程刚启动（$lastMinute 为 null）只算当前分钟。
 * - 防重复触发：以任务 ID 与计划分钟登记唯一 outbox，多个调度进程共享数据库去重。
 *   Redis 不可用时保留待投递记录，由投递进程恢复；重复唤醒由消费租约隔离。
 * - 单个任务的异常只记日志、不影响同一分钟的其他任务；enabledJobs() 本身失败（数据库断线）冒给进程，
 *   由 app\process\Scheduler 记日志后下一分钟重试。
 *
 * 容器单例：无实例态，只有注入的仓储与投递器。
 */
class CronScheduleService extends Service
{
    #[Inject]
    protected CronJobRepository $cronJobRepository;

    #[Inject]
    protected QueueDispatcher $queueDispatcher;

    /** @return \DateTimeImmutable 本次处理到的分钟（秒归零） */
    public function tick(\DateTimeImmutable $now, ?\DateTimeImmutable $lastMinute): \DateTimeImmutable
    {
        $current = self::floorToMinute($now);
        foreach ($this->minutesToProcess($current, $lastMinute) as $minute) {
            $this->fireDueJobs($minute);
        }

        return $current;
    }

    /** @return list<\DateTimeImmutable> 按时间先后排列 */
    private function minutesToProcess(\DateTimeImmutable $current, ?\DateTimeImmutable $lastMinute): array
    {
        if ($lastMinute === null) {
            return [$current];
        }
        $gap = intdiv($current->getTimestamp() - self::floorToMinute($lastMinute)->getTimestamp(), 60);
        if ($gap <= 0) {
            return [];
        }
        $catchup = max(1, (int) config('cron.catchup_minutes', 5));
        if ($gap > $catchup) {
            Log::warning('cron.schedule.catchup_skipped', [
                'last_minute'    => $lastMinute->format('Y-m-d H:i'),
                'current_minute' => $current->format('Y-m-d H:i'),
                'gap_minutes'    => $gap,
            ]);

            return [$current];
        }

        $minutes = [];
        for ($offset = $gap - 1; $offset >= 0; $offset--) {
            $minutes[] = $current->modify("-{$offset} minutes");
        }

        return $minutes;
    }

    private function fireDueJobs(\DateTimeImmutable $minute): void
    {
        foreach ($this->cronJobRepository->enabledJobs() as $job) {
            $id = (int) $job['id'];
            try {
                if (!CronSchedule::isDue((string) $job['expression'], $minute)) {
                    continue;
                }
                $this->queueDispatcher->dispatchReliable(CronJobService::QUEUE, [
                    'cron_job_id'  => $id,
                    'trigger'      => CronJobService::TRIGGER_SCHEDULED,
                    'scheduled_at' => $minute->format('Y-m-d H:i:00'),
                ], 'cron:' . $id . ':' . $minute->format('YmdHi'));
            } catch (\Throwable $e) {
                Log::error('cron.schedule.job_failed', [
                    'cron_job_id' => $id,
                    'minute'      => $minute->format('Y-m-d H:i'),
                    'error'       => mb_substr($e->getMessage(), 0, 500),
                ]);
            }
        }
    }

    private static function floorToMinute(\DateTimeImmutable $time): \DateTimeImmutable
    {
        return $time->setTime((int) $time->format('H'), (int) $time->format('i'));
    }
}
