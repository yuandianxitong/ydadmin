<?php
declare(strict_types=1);

namespace app\repository\user;

use core\base\Repository;

/**
 * 账目/积分日志 Repository 抽象基类
 *
 * 封装了 BalanceLog / PointsLog 等所有"账目流水"类型表的通用查询逻辑：
 *  - 按 user_id / type / user_ids / 日期范围 过滤
 *  - eager loading user 和 operator 昵称
 *  - 分页后统一拍平 nickname 字段
 *
 * 子类只需实现 `getModel()` 指向具体的 Model 即可。
 */
abstract class AbstractLedgerLogRepository extends Repository
{
    /**
     * 搜索列表（管理后台用）
     */
    public function getSearchList(array $params, int $page = 1, int $limit = 20): array
    {
        $query = $this->baseQuery();

        if (!empty($params['user_id'])) {
            $query->where('user_id', $params['user_id']);
        }
        if (!empty($params['type'])) {
            $query->where('type', $params['type']);
        }
        if (!empty($params['user_ids'])) {
            $query->whereIn('user_id', $params['user_ids']);
        }

        $startTime = $params['start_time'] ?? $params['start_date'] ?? null;
        $endTime   = $params['end_time']   ?? $params['end_date']   ?? null;
        if ($startTime) {
            $query->where('created_at', '>=', $startTime);
        }
        if ($endTime) {
            $query->where('created_at', '<=', $endTime . ' 23:59:59');
        }

        $total = $query->count();
        $list  = $this->flattenNicknames(
            $query->page($page, $limit)->select()->toArray()
        );

        return $this->buildPagination($list, $page, $limit, $total);
    }

    /**
     * 获取指定用户的流水（前台用）
     */
    public function getUserLogs(int $userId, int $page = 1, int $limit = 10): array
    {
        $query = $this->baseQuery()->where('user_id', $userId);

        $total = $query->count();
        $list  = $this->flattenNicknames(
            $query->page($page, $limit)->select()->toArray()
        );

        return $this->buildPagination($list, $page, $limit, $total);
    }

    /**
     * 构造带 user / operator eager loading 的基础查询
     */
    protected function baseQuery()
    {
        return $this->model->with([
            'user' => function ($q) {
                $q->field('id, nickname');
            },
            'operator' => function ($q) {
                $q->field('id, nickname');
            },
        ])->order('id', 'desc');
    }

    /**
     * 将 user/operator 关联对象拍平为 user_nickname / operator_name 字段
     */
    protected function flattenNicknames(array $list): array
    {
        foreach ($list as &$item) {
            $item['user_nickname'] = $item['user']['nickname'] ?? '-';
            $item['operator_name'] = $item['operator']['nickname'] ?? '-';
            unset($item['user'], $item['operator']);
        }
        return $list;
    }
}
