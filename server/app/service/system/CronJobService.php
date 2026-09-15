<?php

declare(strict_types=1);

namespace app\service\system;

use app\repository\system\CronJobLogRepository;
use app\repository\system\CronJobRepository;
use core\base\Service;
use core\context\RequestContext;
use core\cron\CronCommandRunner;
use core\cron\CronRunResult;
use core\cron\CronSchedule;
use core\exception\NotFoundException;
use core\exception\ValidationException;
use core\queue\QueueDispatcher;
use DI\Attribute\Inject;
use support\Log;
use support\Redis;

/**
 * 定时任务管理（spec §7）。系统表，不受数据权限约束。
 *
 * 前端契约：列表 / 详情每行同时带 expression 与 cron_expression（同值；列表读前者、表单回填读后者），
 * next_run_at 即时计算——启用时为下一次到点时间，禁用为 null；库里的表达式若已不合法（被人手改过）也给 null，
 * 不编造时间。新增 / 修改的请求字段叫 cron_expression，入库到 expression 列。
 *
 * 「命令首词在白名单」「表达式合法」不写成闭包校验规则（计划设计决定 6）：控制器 validate() 之后在这里判定，
 * 失败抛 ValidationException，响应仍是 422 且错误挂在 command / cron_expression 上。
 *
 * 执行（spec §8.2、§8.3）：定时触发与手动执行都投进 cron-job 队列，由 CronJobConsumer 调 execute()。
 * - 执行锁 cron:running:{id}（SET NX EX lock_ttl，值为随机令牌，Lua 比较后删除）保证同一任务不重叠。
 * - 命令失败（非 0 退出或抛异常）如实写执行日志，不重试；只有写日志这类基础设施异常才抛给队列（进 failed_jobs）。
 * - 手动执行带 token：结果 LPUSH 到 cron:result:{token}（EXPIRE 120），http 进程 BLPOP 最多等 manual_wait_seconds。
 */
class CronJobService extends Service
{
    public const QUEUE = 'cron-job';

    public const TRIGGER_SCHEDULED = 1;

    public const TRIGGER_MANUAL = 2;

    private const RUNNING_KEY = 'cron:running:';

    private const RESULT_KEY = 'cron:result:';

    private const RESULT_TTL = 120;

    private const LAST_RESULT_MAX = 500;

    /** 只删自己持有的锁：值与令牌相同才 DEL（锁过期后被别的执行抢到，不能误删）。 */
    private const RELEASE_LOCK = "if redis.call('GET', KEYS[1]) == ARGV[1] then return redis.call('DEL', KEYS[1]) end return 0";

    #[Inject]
    protected CronJobRepository $cronJobRepository;

    #[Inject]
    protected CronJobLogRepository $cronJobLogRepository;

    #[Inject]
    protected CronCommandRunner $commandRunner;

    #[Inject]
    protected QueueDispatcher $queueDispatcher;

    /**
     * @param array<string, mixed> $params 控制器 validate() 的返回值：keyword、status
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getList(array $params, int $page, int $limit): array
    {
        $result = $this->cronJobRepository->getSearchList($params, $page, $limit);
        $result['list'] = array_map(fn (array $row): array => $this->present($row), $result['list']);

        return $result;
    }

    /** @return array<string, mixed> */
    public function getDetail(int $id): array
    {
        return $this->present($this->findOrFail($id));
    }

    /**
     * @param array<string, mixed> $data 已校验：name、command、cron_expression 必有
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        $command = (string) $data['command'];
        $expression = (string) $data['cron_expression'];
        $this->assertCommandAllowed($command);
        $this->assertExpressionValid($expression);

        $actingUser = RequestContext::actingUser();
        $row = $this->cronJobRepository->create([
            'name'        => (string) $data['name'],
            'command'     => $command,
            'expression'  => $expression,
            'description' => isset($data['description']) ? (string) $data['description'] : null,
            'sort'        => (int) ($data['sort'] ?? 0),
            'status'      => (int) ($data['status'] ?? 1),
            'run_count'   => 0,
            'created_by'  => $actingUser > 0 ? $actingUser : null,
        ]);

        return $this->present($row);
    }

    /** @param array<string, mixed> $data 已校验，字段均可选（部分更新） */
    public function update(int $id, array $data): void
    {
        $this->findOrFail($id);

        $update = [];
        foreach (['name', 'description', 'sort', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }
        if (array_key_exists('command', $data)) {
            $this->assertCommandAllowed((string) $data['command']);
            $update['command'] = (string) $data['command'];
        }
        if (array_key_exists('cron_expression', $data)) {
            $this->assertExpressionValid((string) $data['cron_expression']);
            $update['expression'] = (string) $data['cron_expression'];
        }
        if ($update === []) {
            return;
        }

        $this->cronJobRepository->update($id, $update);
    }

    /** 软删：执行日志保留；调度器（enabledJobs）与消费者（find）都看不到已删任务。 */
    public function delete(int $id): void
    {
        if (!$this->cronJobRepository->delete($id)) {
            throw new NotFoundException();
        }
    }

    public function updateStatus(int $id, int $status): void
    {
        $this->findOrFail($id);
        $this->cronJobRepository->update($id, ['status' => $status]);
    }

    /**
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getLogs(int $id, int $page, int $limit): array
    {
        $this->findOrFail($id);

        return $this->cronJobLogRepository->getListByJob($id, $page, $limit);
    }

    /** @return int 删除条数 */
    public function clearLogs(int $id, int $keepDays): int
    {
        $this->findOrFail($id);

        return $this->cronJobLogRepository->clearByJob($id, $keepDays);
    }

    /**
     * 队列消费者入口。payload：cron_job_id、trigger（1 定时 / 2 手动）、token（仅手动）、scheduled_at（仅定时，便于排查）。
     *
     * @param array<string, mixed> $payload
     */
    public function execute(array $payload): void
    {
        $jobId = (int) ($payload['cron_job_id'] ?? 0);
        $trigger = (int) ($payload['trigger'] ?? self::TRIGGER_SCHEDULED) === self::TRIGGER_MANUAL ? self::TRIGGER_MANUAL : self::TRIGGER_SCHEDULED;
        $token = $trigger === self::TRIGGER_MANUAL ? (string) ($payload['token'] ?? '') : '';

        $job = $this->cronJobRepository->find($jobId);
        if ($job === null) {
            $this->pushResult($token, 0, lang('messages.data_not_found'));

            return;
        }
        if ($trigger === self::TRIGGER_SCHEDULED && (int) $job['status'] !== 1) {
            return;
        }

        $lockKey = self::RUNNING_KEY . $jobId;
        $lockToken = bin2hex(random_bytes(16));
        $lockTtl = max(1, (int) config('cron.lock_ttl', 3600));
        if (Redis::set($lockKey, $lockToken, 'EX', $lockTtl, 'NX') !== true) {
            if ($trigger === self::TRIGGER_MANUAL) {
                $this->pushResult($token, 0, lang('business.cron_job_running'));
            } else {
                Log::info('定时任务上次执行尚未结束，本次跳过', ['cron_job_id' => $jobId, 'scheduled_at' => $payload['scheduled_at'] ?? null]);
            }

            return;
        }

        try {
            $result = $this->commandRunner->run((string) $job['command']);
            $this->recordResult($jobId, $trigger, $result);
            $this->pushResult(
                $token,
                $result->success ? 1 : 0,
                $result->success || $result->error === '' ? $result->output : $result->error
            );
        } finally {
            Redis::eval(self::RELEASE_LOCK, 1, $lockKey, $lockToken);
        }
    }

    /**
     * 手动执行：投递到队列后 BLPOP 等结果，最多 cron.manual_wait_seconds 秒。
     * 超时（含队列进程没启动）返回 status 0 与「已提交执行」文案，真实结果看执行日志。
     *
     * @return array{status: int, output: string}
     */
    public function runNow(int $id): array
    {
        $this->findOrFail($id);

        $token = bin2hex(random_bytes(16));
        $this->queueDispatcher->dispatch(self::QUEUE, [
            'cron_job_id' => $id,
            'trigger'     => self::TRIGGER_MANUAL,
            'token'       => $token,
        ]);

        $wait = max(1, (int) config('cron.manual_wait_seconds', 10));
        $popped = Redis::blPop(self::RESULT_KEY . $token, $wait);
        if (!is_array($popped) || !isset($popped[1])) {
            return ['status' => 0, 'output' => lang('business.cron_run_submitted')];
        }
        $decoded = json_decode((string) $popped[1], true);

        return [
            'status' => (int) (is_array($decoded) ? ($decoded['status'] ?? 0) : 0),
            'output' => (string) (is_array($decoded) ? ($decoded['output'] ?? '') : ''),
        ];
    }

    /** @return array<string, mixed> 未删除的任务行；不存在抛 NotFoundException（code 404） */
    protected function findOrFail(int $id): array
    {
        return $this->cronJobRepository->find($id) ?? throw new NotFoundException();
    }

    /** 同一个事务里写执行日志并回写任务的 last_* 与 run_count；失败原样抛出（交给队列进 failed_jobs）。 */
    private function recordResult(int $jobId, int $trigger, CronRunResult $result): void
    {
        $lastResult = mb_substr($result->success || $result->error === '' ? $result->output : $result->error, 0, self::LAST_RESULT_MAX);

        $this->runInTransaction(function () use ($jobId, $trigger, $result, $lastResult): void {
            $this->cronJobLogRepository->create([
                'cron_job_id' => $jobId,
                'trigger'     => $trigger,
                'status'      => $result->success ? 1 : 0,
                'output'      => $result->output,
                'error'       => $result->error,
                'started_at'  => $result->startedAt,
                'finished_at' => $result->finishedAt,
                'duration'    => $result->durationMs,
                'created_at'  => $result->finishedAt,
            ]);
            $this->cronJobRepository->recordRun($jobId, $result->startedAt, $result->success ? 1 : 0, $lastResult);
        });
    }

    /** 只有手动执行（带 token）才回传；定时触发 token 为空，直接返回。 */
    private function pushResult(string $token, int $status, string $output): void
    {
        if ($token === '') {
            return;
        }
        $key = self::RESULT_KEY . $token;
        Redis::lPush($key, (string) json_encode(['status' => $status, 'output' => $output], JSON_UNESCAPED_UNICODE));
        Redis::expire($key, self::RESULT_TTL);
    }

    private function assertCommandAllowed(string $command): void
    {
        if (!$this->commandRunner->allows($command)) {
            throw new ValidationException(['command' => lang('business.cron_command_not_allowed')]);
        }
    }

    private function assertExpressionValid(string $expression): void
    {
        if (!CronSchedule::isValid($expression)) {
            throw new ValidationException(['cron_expression' => lang('validation.cron_expression_invalid')]);
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function present(array $row): array
    {
        $expression = (string) ($row['expression'] ?? '');
        $row['cron_expression'] = $expression;
        $row['next_run_at'] = null;
        if ((int) ($row['status'] ?? 0) === 1 && CronSchedule::isValid($expression)) {
            try {
                $row['next_run_at'] = CronSchedule::nextRunAt($expression, new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                $row['next_run_at'] = null;
            }
        }

        return $row;
    }
}
