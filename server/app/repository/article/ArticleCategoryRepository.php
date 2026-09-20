<?php

declare(strict_types=1);

namespace app\repository\article;

use app\model\article\ArticleCategory;
use core\base\Model;
use core\base\Repository;
use core\support\Like;

/**
 * 文章栏目仓储（article_categories 表）。
 *
 * 不受数据权限约束：表里既没有 created_by 也没有 dept_id。不声明 $dataScoped，沿用基类默认值。
 */
class ArticleCategoryRepository extends Repository
{
    public const STATUS_ENABLED = 1;

    /** @var list<string> */
    protected array $sortable = ['id', 'sort', 'created_at', 'updated_at'];

    protected ?string $creatorColumn = null;

    protected function getModel(): Model
    {
        return new ArticleCategory();
    }

    /**
     * 扁平列表，供 Service 组树。keyword 滤 name，status 精确匹配。
     *
     * @return list<array<string, mixed>>
     */
    public function listFlat(?string $keyword, mixed $status): array
    {
        $query = $this->query();

        $keyword = trim((string) $keyword);
        if ($keyword !== '') {
            $query->where($this->qualify('name'), 'like', Like::contains($keyword));
        }

        if ($status !== null && $status !== '') {
            $query->where($this->qualify('status'), (int) $status);
        }

        return $this->applyOrder($query, 'sort asc, id asc')->get()->toArray();
    }

    /**
     * 滤完扁平行再组树；父节点被过滤掉时子节点升为根。
     *
     * @return list<array<string, mixed>>
     */
    public function getTree(?string $keyword, mixed $status): array
    {
        return $this->buildTree($this->listFlat($keyword, $status), 0);
    }

    /**
     * 已启用栏目树（表单 options）。exclude_id>0 时去掉该节点及其子孙。
     *
     * @return list<array<string, mixed>>
     */
    public function getOptions(int $excludeId = 0): array
    {
        $rows = $this->listFlat(null, self::STATUS_ENABLED);
        if ($excludeId > 0) {
            $drop = array_flip([$excludeId, ...$this->descendantIds($excludeId)]);
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => !isset($drop[(int) $row['id']])
            ));
        }

        return $this->buildTree($rows, 0);
    }

    public function existsName(string $name, int $excludeId = 0): bool
    {
        $query = $this->query()->where($this->qualify('name'), $name);
        if ($excludeId > 0) {
            $query->where($this->qualify('id'), '<>', $excludeId);
        }

        return $query->exists();
    }

    /**
     * 直接子栏目 id。
     *
     * @return list<int>
     */
    public function childIds(int $id): array
    {
        return array_values(array_map(
            'intval',
            $this->query()->where($this->qualify('parent_id'), $id)->pluck($this->qualify('id'))->all()
        ));
    }

    /**
     * 全部子孙 id（不含自身）。一次拉扁平行，内存 BFS，禁止 Db::。
     *
     * @return list<int>
     */
    public function descendantIds(int $id): array
    {
        $rows = $this->query()->get(['id', 'parent_id'])->toArray();

        $childrenMap = [];
        foreach ($rows as $row) {
            $childrenMap[(int) $row['parent_id']][] = (int) $row['id'];
        }

        $ids = [];
        $queue = [$id];
        while ($queue !== []) {
            $current = array_shift($queue);
            foreach ($childrenMap[$current] ?? [] as $childId) {
                $ids[] = $childId;
                $queue[] = $childId;
            }
        }

        return $ids;
    }

    public function hasChildren(int $id): bool
    {
        return $this->query()->where($this->qualify('parent_id'), $id)->exists();
    }

    /**
     * 扁平数组 → 树形（引用构建，单次遍历）。抄自 MenuRepository::buildTree。
     *
     * @param array<int, array<string, mixed>> $rows 每行须含 'id'/'parent_id'
     * @return array<int, array<string, mixed>>
     */
    private function buildTree(array $rows, int $rootParentId): array
    {
        $mapped = [];
        foreach ($rows as $row) {
            $row['id'] = (int) $row['id'];
            $row['parent_id'] = (int) $row['parent_id'];
            $row['children'] = [];
            $mapped[$row['id']] = $row;
        }

        $tree = [];
        foreach ($mapped as &$item) {
            $pid = $item['parent_id'];
            if ($pid === $rootParentId || !isset($mapped[$pid])) {
                $tree[] = &$item;
            } else {
                $mapped[$pid]['children'][] = &$item;
            }
        }
        unset($item);

        return $tree;
    }
}
