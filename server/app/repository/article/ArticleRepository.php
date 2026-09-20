<?php

declare(strict_types=1);

namespace app\repository\article;

use app\model\article\Article;
use core\base\Model;
use core\base\Repository;
use core\support\Like;
use Illuminate\Database\Eloquent\Builder;

/**
 * 文章仓储（articles 表）。由代码生成器生成，可直接手改。
 *
 * 不受数据权限约束：内容表对持有 article.* 的管理员是同一份，显式 $dataScoped = false。
 * $dataScoped=false 时基类 create() 不会自动填 created_by，由 Service 写入。
 */
class ArticleRepository extends Repository
{
    public const PUBLISHED = 1;

    /** @var list<string> */
    protected array $sortable = ['id', 'created_at', 'updated_at'];

    protected bool $dataScoped = false;

    protected ?string $creatorColumn = 'created_by';

    protected function getModel(): Model
    {
        return new Article();
    }

    /**
     * 单条 + 栏目名。C 端再带 views（view_count 别名）。
     *
     * @return array<string, mixed>|null
     */
    public function findWithCategory(int $id, bool $withViews = false): ?array
    {
        $article = $this->query()->with(['category:id,name'])->find($id);
        if ($article === null) {
            return null;
        }

        return $this->presentCategory($article->toArray(), $withViews);
    }

    /**
     * 管理端列表：keyword=title like，另滤 category_id / status，id desc，带 category_name。
     *
     * @param array<string, mixed> $params
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getAdminList(array $params, int $page, int $limit): array
    {
        $query = $this->query()->with(['category:id,name']);

        $keyword = trim((string) ($params['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where($this->qualify('title'), 'like', Like::contains($keyword));
        }

        if (isset($params['category_id']) && $params['category_id'] !== '') {
            $query->where($this->qualify('category_id'), (int) $params['category_id']);
        }

        if (isset($params['status']) && $params['status'] !== '') {
            $query->where($this->qualify('status'), (int) $params['status']);
        }

        return $this->paginateWithCategory($query, $page, $limit, false);
    }

    /**
     * C 端已发布列表：只认 status=PUBLISHED，不过滤 publish_at。
     *
     * @param array<string, mixed> $params
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getPublishedList(array $params, int $page, int $limit): array
    {
        $query = $this->query()->with(['category:id,name'])
            ->where($this->qualify('status'), self::PUBLISHED);

        if (isset($params['category_id']) && $params['category_id'] !== '') {
            $query->where($this->qualify('category_id'), (int) $params['category_id']);
        }

        return $this->paginateWithCategory($query, $page, $limit, true);
    }

    public function incrementViewCount(int $id): void
    {
        $this->query()->whereKey($id)->increment('view_count');
    }

    /** 未软删文章数（query() 已套 SoftDeletes）。 */
    public function countByCategoryId(int $id): int
    {
        return $this->query()->where($this->qualify('category_id'), $id)->count();
    }

    /**
     * @param Builder<Model> $query
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    private function paginateWithCategory(Builder $query, int $page, int $limit, bool $withViews): array
    {
        $page = max(1, $page);
        $limit = min(self::MAX_PAGE_SIZE, max(1, $limit));
        $total = (clone $query)->count();
        $list = $this->applyOrder($query, 'id desc')->forPage($page, $limit)->get()->toArray();
        $list = array_map(fn (array $row): array => $this->presentCategory($row, $withViews), $list);

        return $this->buildPagination($list, $page, $limit, $total);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function presentCategory(array $row, bool $withViews): array
    {
        $row['category_name'] = is_array($row['category'] ?? null) ? (string) ($row['category']['name'] ?? '') : '';
        unset($row['category']);
        if ($withViews) {
            $row['views'] = (int) ($row['view_count'] ?? 0);
        }

        return $row;
    }
}
