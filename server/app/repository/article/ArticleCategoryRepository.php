<?php

declare(strict_types=1);

namespace app\repository\article;

use app\model\article\ArticleCategory;
use core\base\Model;
use core\base\Repository;
use core\support\Like;

/**
 * 文章栏目仓储（article_categories 表）。由代码生成器生成，可直接手改。
 *
 * 不受数据权限约束：表里既没有 created_by 也没有 dept_id。不声明 $dataScoped，沿用基类默认值。
 */
class ArticleCategoryRepository extends Repository
{
    /** @var list<string> */
    protected array $sortable = ['id', 'sort', 'created_at', 'updated_at'];

    protected ?string $creatorColumn = null;

    protected function getModel(): Model
    {
        return new ArticleCategory();
    }

    /**
     * 列表查询（本任务先返回扁平数组，树形留给后续任务），搜索项：name（模糊）、status（精确）。
     *
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function getArticleCategoryList(array $params): array
    {
        $query = $this->query();

        $searchName = trim((string) ($params['name'] ?? ''));
        if ($searchName !== '') {
            $query->where($this->qualify('name'), 'like', Like::contains($searchName));
        }

        if (isset($params['status']) && $params['status'] !== '') {
            $query->where($this->qualify('status'), (int) $params['status']);
        }

        return $this->applyOrder($query, 'sort asc, id desc')->get()->toArray();
    }

    /**
     * 已启用栏目扁平列表（options 用；exclude_id 留给后续任务）。
     *
     * @return list<array<string, mixed>>
     */
    public function getEnabledOptions(): array
    {
        return $this->query()->where($this->qualify('status'), 1)
            ->orderBy('sort', 'asc')->orderBy('id', 'asc')
            ->get()->toArray();
    }
}
