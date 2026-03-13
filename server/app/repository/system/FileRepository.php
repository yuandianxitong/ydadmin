<?php
declare(strict_types=1);

namespace app\repository\system;

use app\model\system\File;
use core\base\Repository;
use think\Model;

class FileRepository extends Repository
{
    protected function getModel(): Model
    {
        return new File();
    }

    /**
     * 获取文件列表
     */
    public function getFileList(array $where = [], int $page = 1, int $limit = 20, string $order = 'created_at desc'): array
    {
        $query = $this->model->where($where);

        $total = $query->count();
        $list = $query->page($page, $limit)->order($order)->select()->toArray();

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
     * 获取分组列表
     */
    public function getGroups(): array
    {
        return $this->model
            ->field('`group`, COUNT(*) as count')
            ->group('`group`')
            ->order('count desc')
            ->select()
            ->toArray();
    }
}
