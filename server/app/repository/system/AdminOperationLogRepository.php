<?php

declare(strict_types=1);

namespace app\repository\system;

use app\model\system\AdminOperationLog;
use core\base\Model;
use core\base\Repository;
use core\support\Like;
use Illuminate\Database\Eloquent\Builder;

/**
 * 管理员操作日志仓储。
 *
 * 受数据权限约束（spec §5.5）：日志的归属人是被记录的管理员（admin_id）；日志表没有 created_by，
 * 所以 $creatorColumn = null，create() 不自动填。列表、删除、清空都只作用于当前管理员范围内的行。
 */
class AdminOperationLogRepository extends Repository
{
    protected bool $dataScoped = true;

    protected string $ownerColumn = 'admin_id';

    protected ?string $creatorColumn = null;

    protected function getModel(): Model
    {
        return new AdminOperationLog();
    }

    /**
     * @param array<string, mixed> $params keyword（username/action/description 模糊）、method、path（模糊）、start_date、end_date（Y-m-d）
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
            $columns = [$this->qualify('username'), $this->qualify('action'), $this->qualify('description')];
            $query->where(static function (Builder $q) use ($like, $columns): void {
                foreach ($columns as $column) {
                    $q->orWhere($column, 'like', $like);
                }
            });
        }
        $method = strtoupper(trim((string) ($params['method'] ?? '')));
        if ($method !== '') {
            $query->where($this->qualify('method'), $method);
        }
        $path = trim((string) ($params['path'] ?? ''));
        if ($path !== '') {
            $query->where($this->qualify('path'), 'like', Like::contains($path));
        }
        if (!empty($params['start_date'])) {
            $query->where($this->qualify('operation_time'), '>=', (string) $params['start_date'] . ' 00:00:00');
        }
        if (!empty($params['end_date'])) {
            $query->where($this->qualify('operation_time'), '<=', (string) $params['end_date'] . ' 23:59:59');
        }

        $total = (clone $query)->count();
        $list = $query->orderBy($this->qualify('id'), 'desc')->forPage($page, $limit)->get()->toArray();

        return $this->buildPagination($list, $page, $limit, $total);
    }

    /**
     * 写入一条操作日志（OperationLogConsumer 消费队列时调用；投递失败时 AdminLogMiddleware 同步回退调用）。
     * 字符串按列宽截断，避免超长路径或文案让写库失败。operation_time 取载荷里的请求时刻（队列落库有延迟），
     * 缺省才取当前时间。
     *
     * @param array<string, mixed> $data
     */
    public function record(array $data): void
    {
        $this->create([
            'admin_id'       => (int) ($data['admin_id'] ?? 0),
            'username'       => mb_substr((string) ($data['username'] ?? ''), 0, 50),
            'method'         => strtoupper(mb_substr((string) ($data['method'] ?? ''), 0, 10)),
            'path'           => mb_substr((string) ($data['path'] ?? ''), 0, 255),
            'ip'             => mb_substr((string) ($data['ip'] ?? ''), 0, 45),
            'user_agent'     => (string) ($data['user_agent'] ?? ''),
            'action'         => mb_substr((string) ($data['action'] ?? ''), 0, 100),
            'description'    => mb_substr((string) ($data['description'] ?? ''), 0, 255),
            'params'         => (array) ($data['params'] ?? []),
            'result'         => (array) ($data['result'] ?? []),
            'operation_time' => (string) ($data['operation_time'] ?? date('Y-m-d H:i:s')),
            'execution_time' => (float) ($data['execution_time'] ?? 0),
        ]);
    }

    /** 删除当前管理员数据范围内可见的全部操作日志（不软删，一条 DELETE）。 @return int 删除条数 */
    public function clearVisible(): int
    {
        return (int) $this->query()->delete();
    }

    /**
     * 删除 operation_time 早于 $datetime 的操作日志（硬删，一条 DELETE），log:archive 用。
     * 仍从 query() 起手：命令行与队列进程没有管理员上下文，数据权限不过滤（spec §5.3），删的是全表过期行；
     * 若将来从带管理员上下文的请求里调用，只会删到该管理员范围内的行，不会越权。
     *
     * @param string $datetime Y-m-d H:i:s
     * @return int 删除条数
     */
    public function deleteBefore(string $datetime): int
    {
        return (int) $this->query()->where($this->qualify('operation_time'), '<', $datetime)->delete();
    }

    /** 今日操作日志条数（按数据范围，仪表盘用）。 */
    public function getTodayCount(): int
    {
        $today = new \DateTimeImmutable('today');

        return $this->query()
            ->where('operation_time', '>=', $today->format('Y-m-d H:i:s'))
            ->where('operation_time', '<', $today->add(new \DateInterval('P1D'))->format('Y-m-d H:i:s'))
            ->count();
    }

    /**
     * 最近的操作日志（字段裁剪，仪表盘「最近动态」用）。
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRecentActivities(int $limit = 5): array
    {
        return $this->query()
            ->orderByDesc('operation_time')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'username', 'action', 'description', 'method', 'operation_time'])
            ->toArray();
    }
}
