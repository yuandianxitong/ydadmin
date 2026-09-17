<?php

declare(strict_types=1);

namespace app\queue\redis_slow;

use app\queue\ConsumerBase;
use app\service\system\CronJobService;
use DI\Attribute\Inject;

/**
 * cron-job 队列：定时触发与手动执行共用（spec §8.2）。max_attempts = 0——命令本身的失败已写进执行日志，
 * 抛到这里的只可能是基础设施故障（如写日志时数据库不可用），首次失败即进 failed_jobs，不重跑以免重复副作用。
 *
 * 放在慢队列目录（M6b）：命令执行可能很久，不和操作日志抢同一组消费进程。已在 Redis 里排队的任务按队列名消费，迁目录不受影响。
 */
final class CronJobConsumer extends ConsumerBase
{
    public string $queue = 'cron-job';

    #[Inject]
    protected CronJobService $cronJobService;

    public function handle(array $data): void
    {
        $this->cronJobService->execute($data);
    }
}
