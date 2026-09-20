<?php

declare(strict_types=1);

namespace app\repository\announcement;

use app\model\announcement\Announcement;
use core\base\Model;
use core\base\Repository;
use core\support\Like;

/**
 * 公告仓储（announcements 表）。由代码生成器生成，可直接手改。
 *
 * 不受数据权限约束：内容表对持有 announcement.* 的管理员是同一份，显式 $dataScoped = false。
 */
class AnnouncementRepository extends Repository
{
    /** @var list<string> */
    protected array $sortable = ['id', 'sort', 'created_at', 'updated_at'];

    protected bool $dataScoped = false;

    protected ?string $creatorColumn = 'created_by';

    protected function getModel(): Model
    {
        return new Announcement();
    }

    /**
     * 列表查询，搜索项：title（模糊）、status（精确）。
     *
     * @param array<string, mixed> $params
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getAnnouncementList(array $params, int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = min(self::MAX_PAGE_SIZE, max(1, $limit));
        $query = $this->query();

        $searchTitle = trim((string) ($params['title'] ?? ''));
        if ($searchTitle !== '') {
            $query->where($this->qualify('title'), 'like', Like::contains($searchTitle));
        }

        if (isset($params['status']) && $params['status'] !== '') {
            $query->where($this->qualify('status'), (int) $params['status']);
        }

        $total = (clone $query)->count();
        $list = $this->applyOrder($query, 'sort asc, id desc')->forPage($page, $limit)->get()->toArray();

        return $this->buildPagination($list, $page, $limit, $total);
    }
}
