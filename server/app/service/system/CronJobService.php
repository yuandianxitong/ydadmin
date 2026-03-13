<?php
declare(strict_types=1);

namespace app\service\system;

use core\base\Service;
use app\repository\system\CronJobRepository;

class CronJobService extends Service
{
    protected CronJobRepository $repo;

    /**
     * 获取任务列表
     */
    public function getCronJobList(array $params): array
    {
        $page = (int) ($params['page'] ?? 1);
        $limit = (int) ($params['limit'] ?? 20);
        return $this->repo->getListWithStats($params, $page, $limit);
    }

    /**
     * 获取任务详情
     */
    public function getCronJobDetail(int $id): ?array
    {
        return $this->repo->find($id);
    }

    /**
     * 创建任务
     */
    public function createCronJob(array $data): array
    {
        return $this->repo->create($data);
    }

    /**
     * 更新任务
     */
    public function updateCronJob(int $id, array $data): bool
    {
        $job = $this->repo->find($id);
        if (!$job) {
            $this->throwBusinessException(lang('business.task_not_found'));
        }
        return $this->repo->update($id, $data);
    }

    /**
     * 删除任务
     */
    public function deleteCronJob(int $id): bool
    {
        $job = $this->repo->find($id);
        if (!$job) {
            $this->throwBusinessException(lang('business.task_not_found'));
        }
        return $this->repo->delete($id);
    }

    /**
     * 更新状态
     */
    public function updateStatus(int $id, int $status): bool
    {
        $job = $this->repo->find($id);
        if (!$job) {
            $this->throwBusinessException(lang('business.task_not_found'));
        }
        return $this->repo->update($id, ['status' => $status]);
    }

    /**
     * 手动执行任务
     */
    public function runCronJob(int $id): array
    {
        $job = $this->repo->find($id);
        if (!$job) {
            $this->throwBusinessException(lang('business.task_not_found'));
        }

        $startTime = microtime(true);
        $startedAt = date('Y-m-d H:i:s');
        $status = 1;
        $output = '';
        $error = '';

        try {
            // 执行ThinkPHP命令
            ob_start();
            $exitCode = 0;
            exec('cd ' . root_path() . ' && php think ' . escapeshellarg($job['command']) . ' 2>&1', $outputLines, $exitCode);
            $output = implode("\n", $outputLines);
            ob_end_clean();

            if ($exitCode !== 0) {
                $status = 0;
                $error = "Exit code: {$exitCode}";
            }
        } catch (\Throwable $e) {
            $status = 0;
            $error = $e->getMessage();
            if (ob_get_level()) {
                ob_end_clean();
            }
        }

        $duration = (int) ((microtime(true) - $startTime) * 1000);
        $finishedAt = date('Y-m-d H:i:s');

        // 记录日志
        $this->repo->createLog([
            'cron_job_id' => $id,
            'status'      => $status,
            'output'      => $output,
            'error'       => $error,
            'started_at'  => $startedAt,
            'finished_at' => $finishedAt,
            'duration'    => $duration,
        ]);

        // 更新任务状态
        $this->repo->update($id, [
            'last_run_at' => $startedAt,
            'last_result' => mb_substr($output ?: $error, 0, 500),
            'last_status' => $status,
            'run_count'   => $job['run_count'] + 1,
        ]);

        return [
            'status'   => $status,
            'output'   => $output,
            'error'    => $error,
            'duration' => $duration,
        ];
    }

    /**
     * 获取执行日志
     */
    public function getCronJobLogs(int $cronJobId, array $params): array
    {
        $page = (int) ($params['page'] ?? 1);
        $limit = (int) ($params['limit'] ?? 20);
        return $this->repo->getLogs($cronJobId, $page, $limit);
    }

    /**
     * 清理日志
     */
    public function clearLogs(int $cronJobId, int $keepDays = 30): int
    {
        return $this->repo->clearLogs($cronJobId, $keepDays);
    }
}
