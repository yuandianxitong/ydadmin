<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\repository\wechat;

use app\model\wechat\WechatAutoReply;
use core\base\Repository;
use think\Model;

class WechatAutoReplyRepository extends Repository
{
    protected function getModel(): Model
    {
        return new WechatAutoReply();
    }

    /**
     * 分页列表
     */
    public function getPageList(array $where = [], int $page = 1, int $limit = 20): array
    {
        $query = $this->model->where($where)
            ->order('sort_order asc, id desc');

        $total = $query->count();
        $list = $query->page($page, $limit)->select()->toArray();

        return [
            'list' => $list,
            'pagination' => [
                'current_page' => $page,
                'per_page'     => $limit,
                'total'        => $total,
                'last_page'    => (int)ceil($total / $limit),
            ],
        ];
    }

    /**
     * 检查指定类型是否已存在启用的记录
     */
    public function existsEnabledByType(string $type): bool
    {
        return $this->model->where('type', $type)
            ->where('status', 1)
            ->count() > 0;
    }

    /**
     * 精确匹配关键词
     */
    public function findExactKeyword(string $keyword): ?Model
    {
        return $this->model->where('type', 'keyword')
            ->where('match_type', 'exact')
            ->where('keyword', $keyword)
            ->where('status', 1)
            ->order('sort_order asc')
            ->find();
    }

    /**
     * 获取所有启用的模糊匹配规则
     */
    public function getFuzzyKeywordRules(): array
    {
        return $this->model->where('type', 'keyword')
            ->where('match_type', 'fuzzy')
            ->where('status', 1)
            ->order('sort_order asc')
            ->select()
            ->toArray();
    }

    /**
     * 获取指定类型的启用回复
     */
    public function findEnabledByType(string $type): ?Model
    {
        return $this->model->where('type', $type)
            ->where('status', 1)
            ->find();
    }

    /**
     * 获取模型实例（用于更新/删除）
     */
    public function findModel(int $id): ?Model
    {
        return $this->model->find($id);
    }
}
