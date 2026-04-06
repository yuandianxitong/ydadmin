<?php
declare(strict_types=1);

namespace app\service\system;

use core\base\Service;
use core\exception\BusinessException;
use app\repository\system\CronJobRepository;
use think\facade\Console;

class CronJobService extends Service
{
    protected CronJobRepository $repo;

    /**
     * 允许在定时任务中执行的 think 命令白名单
     *
     * 避免通过管理后台配置任意恶意命令。新增可调度的命令时需要显式追加到这里。
     * 键为命令名（`php think` 之后的主命令部分），值用于前端展示。
     */
    protected const ALLOWED_COMMANDS = [
        'log:archive' => '清理过期管理员日志',
    ];

    /**
     * 获取可调度的命令白名单（供前端下拉选择）
     */
    public function getAllowedCommands(): array
    {
        $list = [];
        foreach (self::ALLOWED_COMMANDS as $name => $desc) {
            $list[] = ['name' => $name, 'description' => $desc];
        }
        return $list;
    }

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
        $this->assertCommandAllowed((string) ($data['command'] ?? ''));
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
        if (isset($data['command'])) {
            $this->assertCommandAllowed((string) $data['command']);
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
     *
     * 安全策略：
     *   - 命令必须在白名单中才会执行，避免通过管理后台配置任意 shell 命令
     *   - 使用 `think\facade\Console::call()` 进程内调用，不再 fork shell，彻底规避命令注入
     */
    public function runCronJob(int $id): array
    {
        $job = $this->repo->find($id);
        if (!$job) {
            $this->throwBusinessException(lang('business.task_not_found'));
        }

        $this->assertCommandAllowed((string) $job['command']);

        $startTime = microtime(true);
        $startedAt = date('Y-m-d H:i:s');
        $status = 1;
        $output = '';
        $error = '';

        try {
            // 进程内调用 think 命令，避免 exec 相关的命令注入风险
            $output = (string) Console::call((string) $job['command']);
        } catch (\Throwable $e) {
            $status = 0;
            $error = $e->getMessage();
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

    /**
     * 校验命令是否在白名单中
     *
     * 命令取第一个 token（忽略后续参数），只要 token 在 ALLOWED_COMMANDS 键中即放行。
     */
    protected function assertCommandAllowed(string $command): void
    {
        $command = trim($command);
        if ($command === '') {
            throw new BusinessException(lang('business.cron_command_empty'));
        }

        $mainCommand = strtok($command, " \t") ?: $command;
        if (!array_key_exists($mainCommand, self::ALLOWED_COMMANDS)) {
            throw new BusinessException(lang('business.cron_command_not_allowed') . ': ' . $mainCommand);
        }
    }
}
