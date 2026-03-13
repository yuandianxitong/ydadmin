<?php
declare(strict_types=1);

namespace app\repository\message;

use app\model\message\MessageTemplate;
use core\base\Repository;
use think\Model;

class MessageTemplateRepository extends Repository
{
    protected function getModel(): Model
    {
        return new MessageTemplate();
    }

    /**
     * 获取模板列表（支持搜索）
     */
    public function getSearchList(array $params, int $page = 1, int $limit = 20): array
    {
        $query = $this->model->order('id', 'desc');

        if (!empty($params['keyword'])) {
            $query->where(function ($q) use ($params) {
                $q->where('name', 'like', '%' . $params['keyword'] . '%')
                  ->whereOr('code', 'like', '%' . $params['keyword'] . '%');
            });
        }
        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', (int) $params['status']);
        }

        $total = $query->count();
        $list = $query->page($page, $limit)->select()->toArray();

        return [
            'list' => $list,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $total,
                'last_page' => (int) ceil($total / $limit),
            ],
        ];
    }

    /**
     * 根据模板标识查找已启用的模板
     */
    public function findEnabledByCode(string $code): ?Model
    {
        return $this->model->where('code', $code)->where('status', 1)->find();
    }

    /**
     * 检查模板标识是否存在
     */
    public function existsCode(string $code, int $excludeId = 0): bool
    {
        $query = $this->model->where('code', $code);
        if ($excludeId > 0) {
            $query->where('id', '<>', $excludeId);
        }
        return $query->count() > 0;
    }

    /**
     * 获取模型实例（用于更新/删除操作）
     */
    public function findModel(int $id): ?Model
    {
        return $this->model->find($id);
    }
}
