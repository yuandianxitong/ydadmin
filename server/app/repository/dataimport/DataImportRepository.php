<?php
declare(strict_types=1);

namespace app\repository\dataimport;

use app\model\dataimport\DataImport;
use core\base\Repository;
use think\Model;

class DataImportRepository extends Repository
{
    protected function getModel(): Model
    {
        return new DataImport();
    }

    /**
     * 获取模型实例（用于更新操作）
     */
    public function findModel(int $id): ?DataImport
    {
        return DataImport::find($id);
    }

    /**
     * 搜索导入记录列表（管理端）
     */
    public function getSearchList(array $params, int $page = 1, int $limit = 20): array
    {
        $where = [];

        if (!empty($params['module'])) {
            $where[] = ['module', '=', $params['module']];
        }

        return $this->getList($where, $page, $limit, 'id desc');
    }
}
