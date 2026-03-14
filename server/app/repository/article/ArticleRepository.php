<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\repository\article;

use app\model\article\Article;
use core\base\Repository;
use think\Model;

class ArticleRepository extends Repository
{
    protected function getModel(): Model
    {
        return new Article();
    }

    /**
     * 获取模型实例（用于更新操作）
     */
    public function findModel(int $id): ?Article
    {
        return Article::find($id);
    }

    /**
     * 搜索文章列表（管理端）
     */
    public function getSearchList(array $params, int $page = 1, int $limit = 20): array
    {
        $where = [];

        if (isset($params['status']) && $params['status'] !== '') {
            $where[] = ['status', '=', (int) $params['status']];
        }
        if (!empty($params['category_id'])) {
            $where[] = ['category_id', '=', (int) $params['category_id']];
        }
        if (!empty($params['keyword'])) {
            $where[] = ['title', 'like', "%{$params['keyword']}%"];
        }

        return $this->getList($where, $page, $limit, 'id desc');
    }

    /**
     * 获取已发布的文章列表（C端）
     */
    public function getPublishedList(int $page = 1, int $limit = 10, int $categoryId = 0): array
    {
        $where = [
            ['status', '=', Article::STATUS_PUBLISHED],
            ['publish_at', '<=', date('Y-m-d H:i:s')],
        ];

        if ($categoryId > 0) {
            $where[] = ['category_id', '=', $categoryId];
        }

        return $this->getList($where, $page, $limit, 'id desc');
    }

    /**
     * 递增浏览量
     */
    public function incrementViewCount(int $id): void
    {
        $this->inc(['id' => $id], 'view_count');
    }
}
