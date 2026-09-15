<?php

declare(strict_types=1);

namespace app\repository\system;

use app\model\system\CronJob;
use core\base\Model;
use core\base\Repository;
use core\support\Like;
use Illuminate\Database\Eloquent\Builder;

/**
 * 定时任务仓储（系统表，不受数据权限约束）。
 *
 * $dataScoped = false，所以基类 create() 不会自动填 created_by——由 CronJobService::create() 显式传入。
 * 软删行由 SoftDeletes 全局作用域自动排除：调度器与消费者看不到已删除的任务。
 */
class CronJobRepository extends Repository
{
    /** last_result 列宽。 */
    private const RESULT_LIMIT = 500;

    /** @var list<string> */
    protected array $sortable = ['id', 'sort', 'created_at'];

    protected function getModel(): Model
    {
        return new CronJob();
    }

    /**
     * @param array<string, mixed> $params keyword（name/command 模糊）、status（0/1）
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getSearchList(array $params, int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = min(self::MAX_PAGE_SIZE, max(1, $limit));
        $query = $this->query();

        $keyword = trim((string) ($params['keyword'] ?? ''));
        if ($keyword !== '') {
            $like = Like::contains($keyword);
            $columns = [$this->qualify('name'), $this->qualify('command')];
            $query->where(static function (Builder $q) use ($like, $columns): void {
                foreach ($columns as $column) {
                    $q->orWhere($column, 'like', $like);
                }
            });
        }
        if (isset($params['status']) && $params['status'] !== '') {
            $query->where($this->qualify('status'), (int) $params['status']);
        }

        $total = (clone $query)->count();
        $list = $this->applyOrder($query, 'sort asc, id desc')->forPage($page, $limit)->get()->toArray();

        return $this->buildPagination($list, $page, $limit, $total);
    }

    /**
     * 调度器每分钟取一次：启用且未删除的任务，sort、id 升序。
     *
     * @return list<array<string, mixed>>
     */
    public function enabledJobs(): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->applyOrder($this->query()->where($this->qualify('status'), 1), 'sort asc, id asc')->get()->toArray();

        return $rows;
    }

    /** 回写上次执行信息并累加 run_count（一条 UPDATE，原子自增）。$lastResult 截断到 500 字。 */
    public function recordRun(int $id, string $lastRunAt, int $lastStatus, string $lastResult): void
    {
        $this->query()->where($this->qualify('id'), $id)->increment('run_count', 1, [
            'last_run_at' => $lastRunAt,
            'last_status' => $lastStatus,
            'last_result' => mb_substr($lastResult, 0, self::RESULT_LIMIT),
        ]);
    }
}
