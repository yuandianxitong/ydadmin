<?php

declare(strict_types=1);

namespace app\service\system;

use app\repository\system\CronJobLogRepository;
use app\repository\system\CronJobRepository;
use core\base\Service;
use core\context\RequestContext;
use core\cron\CronCommandRunner;
use core\cron\CronSchedule;
use core\exception\NotFoundException;
use core\exception\ValidationException;
use DI\Attribute\Inject;

/**
 * 定时任务管理（spec §7）。系统表，不受数据权限约束。
 *
 * 前端契约：列表 / 详情每行同时带 expression 与 cron_expression（同值；列表读前者、表单回填读后者），
 * next_run_at 即时计算——启用时为下一次到点时间，禁用为 null；库里的表达式若已不合法（被人手改过）也给 null，
 * 不编造时间。新增 / 修改的请求字段叫 cron_expression，入库到 expression 列。
 *
 * 「命令首词在白名单」「表达式合法」不写成闭包校验规则（计划设计决定 6）：控制器 validate() 之后在这里判定，
 * 失败抛 ValidationException，响应仍是 422 且错误挂在 command / cron_expression 上。
 */
class CronJobService extends Service
{
    #[Inject]
    protected CronJobRepository $cronJobRepository;

    #[Inject]
    protected CronJobLogRepository $cronJobLogRepository;

    #[Inject]
    protected CronCommandRunner $commandRunner;

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

    /** @return array<string, mixed> 未删除的任务行；不存在抛 NotFoundException（code 404） */
    protected function findOrFail(int $id): array
    {
        return $this->cronJobRepository->find($id) ?? throw new NotFoundException();
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
