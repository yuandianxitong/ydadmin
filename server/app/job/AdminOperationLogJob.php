<?php
declare(strict_types=1);

namespace app\job;

use app\repository\system\AdminOperationLogRepository;
use think\queue\Job;
use think\facade\Log;

class AdminOperationLogJob
{
    public function fire(Job $job, array $data): void
    {
        try {
            /** @var AdminOperationLogRepository $logRepository */
            $logRepository = app(AdminOperationLogRepository::class);
            $logRepository->record($data);
            $job->delete();
        } catch (\Throwable $e) {
            Log::error('操作日志写入失败', [
                'error' => $e->getMessage(),
                'data'  => $data,
            ]);
            if ($job->attempts() >= 3) {
                $job->delete();
            } else {
                $job->release(10);
            }
        }
    }
}
