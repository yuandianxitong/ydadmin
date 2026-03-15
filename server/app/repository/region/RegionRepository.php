<?php
declare(strict_types=1);

namespace app\repository\region;

use app\model\region\Region;
use core\base\Repository;
use think\Model;

class RegionRepository extends Repository
{
    protected function getModel(): Model
    {
        return new Region();
    }

    /**
     * 获取模型实例（用于更新操作）
     */
    public function findModel(int $id): ?Region
    {
        return Region::find($id);
    }

    /**
     * 根据父级ID获取子级列表
     */
    public function getByParentId(int $parentId): array
    {
        return $this->getAll(
            [['parent_id', '=', $parentId], ['status', '=', 1]],
            'sort asc, id asc'
        );
    }

    /**
     * 获取地区树（用于级联选择器）
     */
    public function getTree(): array
    {
        $all = $this->getAll([['status', '=', 1]], 'sort asc, id asc');
        return $this->buildTree($all, 0);
    }

    /**
     * 递归构建树形结构
     */
    protected function buildTree(array $items, int $parentId): array
    {
        $tree = [];
        foreach ($items as $item) {
            if ((int) $item['parent_id'] === $parentId) {
                $children = $this->buildTree($items, (int) $item['id']);
                $node = [
                    'value' => $item['id'],
                    'label' => $item['name'],
                ];
                if (!empty($children)) {
                    $node['children'] = $children;
                }
                $tree[] = $node;
            }
        }
        return $tree;
    }

    /**
     * 搜索地区列表（管理端）
     */
    public function getSearchList(array $params, int $page = 1, int $limit = 20): array
    {
        $where = [];

        // 有关键词搜索时跨所有层级查询，否则按 parent_id 分级查询
        if (!empty($params['keyword'])) {
            $where[] = ['name', 'like', "%{$params['keyword']}%"];
        } else {
            $parentId = (isset($params['parent_id']) && $params['parent_id'] !== '') ? (int) $params['parent_id'] : 0;
            $where[] = ['parent_id', '=', $parentId];
        }
        if (isset($params['level']) && $params['level'] !== '') {
            $where[] = ['level', '=', (int) $params['level']];
        }

        return $this->getList($where, $page, $limit, 'sort asc, id asc');
    }
}
