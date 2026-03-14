<?php
declare(strict_types=1);

namespace app\repository\version;

use app\model\version\AppVersion;
use core\base\Repository;
use think\Model;

class AppVersionRepository extends Repository
{
    protected function getModel(): Model
    {
        return new AppVersion();
    }

    /**
     * 获取模型实例（用于更新操作）
     */
    public function findModel(int $id): ?AppVersion
    {
        return AppVersion::find($id);
    }

    /**
     * 获取平台最新版本（按version_code倒序取第一条）
     */
    public function getLatestVersion(string $platform): ?array
    {
        $result = $this->model
            ->where('platform', $platform)
            ->where('status', 1)
            ->order('version_code desc')
            ->find();
        return $result ? $result->toArray() : null;
    }

    /**
     * 搜索版本列表（管理端）
     */
    public function getSearchList(array $params, int $page = 1, int $limit = 20): array
    {
        $where = [];

        if (!empty($params['platform'])) {
            $where[] = ['platform', '=', $params['platform']];
        }
        if (isset($params['status']) && $params['status'] !== '') {
            $where[] = ['status', '=', (int) $params['status']];
        }

        return $this->getList($where, $page, $limit, 'version_code desc');
    }
}
