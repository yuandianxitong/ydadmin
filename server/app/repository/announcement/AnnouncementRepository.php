<?php

declare(strict_types=1);

namespace app\repository\announcement;

use app\model\announcement\Announcement;
use core\base\Model;
use core\base\Repository;
use core\support\Like;
use Illuminate\Database\Eloquent\Builder;

/**
 * 公告仓储（announcements 表）。由代码生成器生成，可直接手改。
 *
 * 不受数据权限约束：内容表对持有 announcement.* 的管理员是同一份，显式 $dataScoped = false。
 * $dataScoped=false 时基类 create() 不会自动填 created_by，由 Service 写入。
 */
class AnnouncementRepository extends Repository
{
    public const PUBLISHED = 1;

    /** @var list<string> */
    protected array $sortable = ['id', 'sort', 'created_at', 'updated_at'];

    protected bool $dataScoped = false;

    protected ?string $creatorColumn = 'created_by';

    protected function getModel(): Model
    {
        return new Announcement();
    }

    /**
     * 管理端列表：keyword=title like，另滤 type / status，sort asc, id desc。
     *
     * @param array<string, mixed> $params
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getAnnouncementList(array $params, int $page, int $limit): array
    {
        $query = $this->query();

        $keyword = trim((string) ($params['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where($this->qualify('title'), 'like', Like::contains($keyword));
        }

        if (isset($params['type']) && $params['type'] !== '') {
            $query->where($this->qualify('type'), (int) $params['type']);
        }

        if (isset($params['status']) && $params['status'] !== '') {
            $query->where($this->qualify('status'), (int) $params['status']);
        }

        return $this->paginateSorted($query, $page, $limit);
    }

    /**
     * C 端已发布列表：只认 status=PUBLISHED，不过滤 publish_at。
     *
     * @param array<string, mixed> $params
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getPublishedList(array $params, int $page, int $limit): array
    {
        $query = $this->query()->where($this->qualify('status'), self::PUBLISHED);

        if (isset($params['type']) && $params['type'] !== '') {
            $query->where($this->qualify('type'), (int) $params['type']);
        }

        return $this->paginateSorted($query, $page, $limit);
    }

    /**
     * @param Builder<Model> $query
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    private function paginateSorted(Builder $query, int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = min(self::MAX_PAGE_SIZE, max(1, $limit));
        $total = (clone $query)->count();
        $list = $this->applyOrder($query, 'sort asc, id desc')->forPage($page, $limit)->get()->toArray();

        return $this->buildPagination($list, $page, $limit, $total);
    }
}
