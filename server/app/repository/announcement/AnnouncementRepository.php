<?php
declare(strict_types=1);

namespace app\repository\announcement;

use app\model\announcement\Announcement;
use core\base\Repository;
use think\Model;

class AnnouncementRepository extends Repository
{
    protected function getModel(): Model
    {
        return new Announcement();
    }

    /**
     * 获取模型实例（用于更新操作）
     */
    public function findModel(int $id): ?Announcement
    {
        return Announcement::find($id);
    }

    /**
     * 搜索公告列表（管理端）
     */
    public function getSearchList(array $params, int $page = 1, int $limit = 20): array
    {
        $where = [];

        if (isset($params['status']) && $params['status'] !== '') {
            $where[] = ['status', '=', (int) $params['status']];
        }
        if (isset($params['type']) && $params['type'] !== '') {
            $where[] = ['type', '=', (int) $params['type']];
        }
        if (!empty($params['keyword'])) {
            $where[] = ['title', 'like', "%{$params['keyword']}%"];
        }

        return $this->getList($where, $page, $limit, 'sort asc, id desc');
    }

    /**
     * 获取已发布的公告列表（C端）
     */
    public function getPublishedList(int $page = 1, int $limit = 10): array
    {
        $where = [
            ['status', '=', Announcement::STATUS_PUBLISHED],
            ['publish_at', '<=', date('Y-m-d H:i:s')],
        ];
        return $this->getList($where, $page, $limit, 'sort asc, id desc');
    }
}
