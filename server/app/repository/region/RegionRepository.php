<?php

declare(strict_types=1);

namespace app\repository\region;

use app\model\region\Region;
use core\base\Model;
use core\base\Repository;
use core\support\Like;

/**
 * 地区仓储（regions 表）。
 *
 * 不受数据权限约束：表里既没有 created_by 也没有 dept_id。不声明 $dataScoped，沿用基类默认值。
 */
class RegionRepository extends Repository
{
    public const STATUS_ENABLED = 1;

    /** @var list<string> */
    protected array $sortable = ['id', 'sort'];

    protected ?string $creatorColumn = null;

    protected function getModel(): Model
    {
        return new Region();
    }

    /**
     * 管理端列表：有 keyword 则跨层级 name LIKE；否则按 parent_id（缺省 0）。
     * 可选 level；不过滤 status。
     *
     * @param array<string, mixed> $params
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getSearchList(array $params, int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = min(self::MAX_PAGE_SIZE, max(1, $limit));
        $query = $this->query();

        $keyword = trim((string) ($params['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where($this->qualify('name'), 'like', Like::contains($keyword));
        } else {
            $parentId = (isset($params['parent_id']) && $params['parent_id'] !== '')
                ? (int) $params['parent_id']
                : 0;
            $query->where($this->qualify('parent_id'), $parentId);
        }

        if (isset($params['level']) && $params['level'] !== '') {
            $query->where($this->qualify('level'), (int) $params['level']);
        }

        $total = (clone $query)->count();
        $list = $this->applyOrder($query, 'sort asc, id asc')->forPage($page, $limit)->get()->toArray();

        return $this->buildPagination($list, $page, $limit, $total);
    }

    /**
     * 全部启用地区，供组树。
     *
     * @return list<array<string, mixed>>
     */
    public function listEnabled(): array
    {
        return $this->applyOrder(
            $this->query()->where($this->qualify('status'), self::STATUS_ENABLED),
            'sort asc, id asc'
        )->get()->toArray();
    }

    /**
     * 指定父级下的启用子级（完整行）。
     *
     * @return list<array<string, mixed>>
     */
    public function getByParentId(int $parentId): array
    {
        return $this->applyOrder(
            $this->query()
                ->where($this->qualify('parent_id'), $parentId)
                ->where($this->qualify('status'), self::STATUS_ENABLED),
            'sort asc, id asc'
        )->get()->toArray();
    }

    /** 是否有直接子级（含禁用）。 */
    public function hasChildren(int $id): bool
    {
        return $this->query()->where($this->qualify('parent_id'), $id)->exists();
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

    /**
     * code 是否已被占用（表上有唯一索引，Service 层写入前调它查重）。
     */
    public function existsByCode(string $value, ?int $excludeId = null): bool
    {
        $query = $this->query()->where($this->qualify('code'), $value);
        if ($excludeId !== null) {
            $query->where($this->qualify('id'), '<>', $excludeId);
        }

        return $query->exists();
    }

    /**
     * 扁平行 → 级联选择器节点：只留 value / label / 非空 children。
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array{value: int, label: string, children?: list<array<string, mixed>>}>
     */
    public function buildValueLabelTree(array $rows): array
    {
        $mapped = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $mapped[$id] = [
                'value'     => $id,
                'label'     => (string) $row['name'],
                'parent_id' => (int) $row['parent_id'],
                'children'  => [],
            ];
        }

        $tree = [];
        foreach ($mapped as &$item) {
            $pid = $item['parent_id'];
            if ($pid === 0 || !isset($mapped[$pid])) {
                $tree[] = &$item;
            } else {
                $mapped[$pid]['children'][] = &$item;
            }
        }
        unset($item);

        return $this->normalizeValueLabelNodes($tree);
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @return list<array{value: int, label: string, children?: list<array<string, mixed>>}>
     */
    private function normalizeValueLabelNodes(array $nodes): array
    {
        $out = [];
        foreach ($nodes as $node) {
            $item = [
                'value' => (int) $node['value'],
                'label' => (string) $node['label'],
            ];
            $children = $this->normalizeValueLabelNodes($node['children'] ?? []);
            if ($children !== []) {
                $item['children'] = $children;
            }
            $out[] = $item;
        }

        return $out;
    }
}
