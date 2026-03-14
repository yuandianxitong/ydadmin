<?php
declare(strict_types=1);

namespace app\repository\feedback;

use app\model\feedback\Feedback;
use core\base\Repository;
use think\Model;

class FeedbackRepository extends Repository
{
    protected function getModel(): Model
    {
        return new Feedback();
    }

    /**
     * 搜索反馈列表（管理端）
     */
    public function getSearchList(array $params, int $page = 1, int $limit = 20): array
    {
        $where = [];

        if (isset($params['status']) && $params['status'] !== '') {
            $where[] = ['status', '=', (int) $params['status']];
        }
        if (!empty($params['type'])) {
            $where[] = ['type', '=', $params['type']];
        }
        if (!empty($params['user_id'])) {
            $where[] = ['user_id', '=', (int) $params['user_id']];
        }
        if (!empty($params['keyword'])) {
            $where[] = ['content', 'like', "%{$params['keyword']}%"];
        }

        return $this->getList($where, $page, $limit, 'id desc');
    }

    /**
     * 获取用户的反馈列表（C端）
     */
    public function getUserFeedbacks(int $userId, int $page = 1, int $limit = 10): array
    {
        $where = [['user_id', '=', $userId]];
        return $this->getList($where, $page, $limit, 'id desc');
    }
}
