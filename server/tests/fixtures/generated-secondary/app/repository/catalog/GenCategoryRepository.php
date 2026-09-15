<?php

declare(strict_types=1);

namespace app\repository\catalog;

use app\model\catalog\GenCategory;
use core\base\Model;
use core\base\Repository;
use core\support\Like;

/**
 * 生成器夹具表二（无状态列/图片列/创建人列）仓储（gen_categories 表）。由代码生成器生成，可直接手改。
 *
 * 不受数据权限约束：表里既没有 created_by 也没有 dept_id——照 FileRepository 的先例显式写出来，免得后来的人以为漏配。
 */
class GenCategoryRepository extends Repository
{
    /** @var list<string> */
    protected array $sortable = ['id', 'sort', 'created_at', 'updated_at'];

    protected bool $dataScoped = false;

    protected ?string $creatorColumn = null;

    protected function getModel(): Model
    {
        return new GenCategory();
    }

    /**
     * 列表查询，搜索项：name（模糊）。
     *
     * @param array<string, mixed> $params
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getGenCategoryList(array $params, int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = min(self::MAX_PAGE_SIZE, max(1, $limit));
        $query = $this->query();

        $searchName = trim((string) ($params['name'] ?? ''));
        if ($searchName !== '') {
            $query->where($this->qualify('name'), 'like', Like::contains($searchName));
        }

        $total = (clone $query)->count();
        $list = $this->applyOrder($query, 'sort asc, id desc')->forPage($page, $limit)->get()->toArray();

        return $this->buildPagination($list, $page, $limit, $total);
    }
}
