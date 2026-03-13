<?php
declare(strict_types=1);

namespace app\repository\system;

use app\model\system\Dictionary;
use core\base\Repository;
use think\Model;

class DictionaryRepository extends Repository
{
    protected function getModel(): Model
    {
        return new Dictionary();
    }

    /**
     * 获取字典列表（包含字典项数量）
     */
    public function getListWithItemCount(array $where = [], int $page = 1, int $limit = 15): array
    {
        $query = $this->model->withCount(['items'])->where($where);

        $total = $query->count();
        $list = $query->page($page, $limit)
            ->order('sort asc, created_at desc')
            ->select()
            ->toArray();

        return [
            'list'       => $list,
            'pagination' => [
                'current_page' => $page,
                'per_page'     => $limit,
                'total'        => $total,
                'last_page'    => (int)ceil($total / $limit),
            ],
        ];
    }

    /**
     * 获取字典详情（包含字典项）
     */
    public function getDetailWithItems(int $id): ?array
    {
        $result = $this->model->with(['items'])->find($id);
        return $result ? $result->toArray() : null;
    }

    /**
     * 根据 code 获取字典（包含启用的字典项）
     */
    public function getByCode(string $code): ?array
    {
        $result = $this->model
            ->where('code', $code)
            ->where('status', 1)
            ->with(['items' => function ($query) {
                $query->where('status', 1)->order('sort asc, id asc');
            }])
            ->find();
        return $result ? $result->toArray() : null;
    }

    /**
     * 检查 code 是否已存在
     */
    public function existsCode(string $code, int $excludeId = 0): bool
    {
        $query = $this->model->where('code', $code);
        if ($excludeId > 0) {
            $query->where('id', '<>', $excludeId);
        }
        return $query->count() > 0;
    }
}
