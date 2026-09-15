<?php

declare(strict_types=1);

namespace app\repository\system;

use app\model\system\CronJobLog;
use core\base\Model;
use core\base\Repository;

/**
 * 定时任务执行日志仓储（不受数据权限约束；表里没有创建人列）。
 * 清空是硬删：一律走 query()->delete()（规则五禁止 forceDelete()，日志表本身也没有软删）。
 */
class CronJobLogRepository extends Repository
{
    protected ?string $creatorColumn = null;

    protected function getModel(): Model
    {
        return new CronJobLog();
    }

    /**
     * 某个任务的执行日志，id 倒序。
     *
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getListByJob(int $cronJobId, int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = min(self::MAX_PAGE_SIZE, max(1, $limit));
        $query = $this->query()->where($this->qualify('cron_job_id'), $cronJobId);

        $total = (clone $query)->count();
        $list = $query->orderBy($this->qualify('id'), 'desc')->forPage($page, $limit)->get()->toArray();

        return $this->buildPagination($list, $page, $limit, $total);
    }

    /**
     * 清理某个任务的日志。$keepDays = 0 删除全部；否则删除 created_at 早于「今天 0 点往前 $keepDays 天」的行
     * （恰好等于截止点的保留）。
     *
     * @return int 删除条数
     */
    public function clearByJob(int $cronJobId, int $keepDays): int
    {
        $query = $this->query()->where($this->qualify('cron_job_id'), $cronJobId);
        if ($keepDays > 0) {
            $cutoff = (new \DateTimeImmutable('today'))->modify("-{$keepDays} days")->format('Y-m-d H:i:s');
            $query->where($this->qualify('created_at'), '<', $cutoff);
        }

        return (int) $query->delete();
    }
}
