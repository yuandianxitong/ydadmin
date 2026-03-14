<?php
declare(strict_types=1);

namespace app\repository\agreement;

use app\model\agreement\Agreement;
use core\base\Repository;
use think\Model;

class AgreementRepository extends Repository
{
    protected function getModel(): Model
    {
        return new Agreement();
    }

    /**
     * 获取模型实例（用于更新操作）
     */
    public function findModel(int $id): ?Agreement
    {
        return Agreement::find($id);
    }

    /**
     * 根据编码查找启用的协议
     */
    public function findByCode(string $code): ?array
    {
        return $this->findWhere([
            ['code', '=', $code],
            ['status', '=', 1],
        ]);
    }

    /**
     * 搜索协议列表（管理端）
     */
    public function getSearchList(array $params, int $page = 1, int $limit = 20): array
    {
        $where = [];

        if (isset($params['status']) && $params['status'] !== '') {
            $where[] = ['status', '=', (int) $params['status']];
        }
        if (!empty($params['keyword'])) {
            $where[] = ['title', 'like', "%{$params['keyword']}%"];
        }

        return $this->getList($where, $page, $limit, 'id desc');
    }
}
