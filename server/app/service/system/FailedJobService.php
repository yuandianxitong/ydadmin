<?php

declare(strict_types=1);

namespace app\service\system;

use app\repository\system\FailedJobRepository;
use core\base\Service;
use core\helper\SensitiveDataMasker;
use core\queue\QueueDispatcher;
use DI\Attribute\Inject;
use support\Log;

/**
 * 队列失败任务（M3，spec §8.5）。app\queue\ConsumerBase 在最后一次失败时调 record()；三个 queue:* 命令调其余方法。
 *
 * 载荷写入前脱敏：审计看得到字段、看不到凭据。代价是 retry 重投的也是脱敏后的数据——带凭据的任务重投后
 * 会以 '***' 执行；本期两个队列的载荷（操作日志本身就已脱敏、定时任务只有 id 与结果令牌）都不依赖凭据。
 */
class FailedJobService extends Service
{
    private const EXCEPTION_MAX_LENGTH = 5000;

    #[Inject]
    protected FailedJobRepository $failedJobRepository;

    #[Inject]
    protected QueueDispatcher $queueDispatcher;

    /** @param array<string, mixed> $data 任务数据（未脱敏） */
    public function record(string $queue, array $data, \Throwable $e, int $attempts): void
    {
        $this->failedJobRepository->record([
            'queue'     => mb_substr($queue, 0, 100),
            'payload'   => SensitiveDataMasker::mask($data),
            'exception' => mb_substr(get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString(), 0, self::EXCEPTION_MAX_LENGTH),
            'attempts'  => max(0, $attempts),
            'failed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @return list<array<string, mixed>> 新的在前 */
    public function latest(int $limit): array
    {
        return $this->failedJobRepository->latest($limit);
    }

    /**
     * 重新投递（包裹是新的，attempts 从 0 开始）。投递成功才删除原记录；投递失败（队列已不登记、Redis 不可用）
     * 的保留原记录并记 warning。sync 驱动下重投若再次失败，会由消费者写入一条新的失败记录、原记录照常删除。
     *
     * @param int|null $id null 表示全部（按 id 升序）
     * @return int 重新投递成功的条数
     */
    public function retry(?int $id): int
    {
        $ids = $id === null ? $this->failedJobRepository->ids() : [$id];
        $count = 0;
        foreach ($ids as $jobId) {
            $job = $this->failedJobRepository->findById($jobId);
            if ($job === null) {
                continue;
            }
            try {
                $this->queueDispatcher->dispatch((string) $job['queue'], (array) ($job['payload'] ?? []));
            } catch (\Throwable $e) {
                Log::warning("失败任务 {$jobId} 重新投递失败，已保留原记录：" . $e->getMessage());

                continue;
            }
            $this->failedJobRepository->deleteById($jobId);
            $count++;
        }

        return $count;
    }

    /**
     * @param int|null $days null 删除全部；否则删除 failed_at 早于「现在 - days 天」的行
     * @return int 删除条数
     */
    public function flush(?int $days): int
    {
        $before = $days === null ? null : date('Y-m-d H:i:s', time() - max(0, $days) * 86400);

        return $this->failedJobRepository->deleteOlderThan($before);
    }
}
