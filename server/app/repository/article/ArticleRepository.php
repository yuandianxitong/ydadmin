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
     * 获取文章详情（带分类名称）
     */
    public function findWithCategory(int $id): ?array
    {
        $article = $this->model->with(['category'])->find($id);
        if (!$article) {
            return null;
        }
        $data = $article->toArray();
        $data['category_name'] = $data['category']['name'] ?? '';
        $data['views'] = $data['view_count'] ?? 0;
        unset($data['category']);
        return $data;
    }

    /**
     * 搜索文章列表（管理端，带分类名称）
     */
    public function getSearchList(array $params, int $page = 1, int $limit = 20): array
    {
        $query = $this->model->with(['category']);

        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', '=', (int) $params['status']);
        }
        if (!empty($params['category_id'])) {
            $query->where('category_id', '=', (int) $params['category_id']);
        }
        if (!empty($params['keyword'])) {
            $query->where('title', 'like', "%{$params['keyword']}%");
        }

        $total = $query->count();
        $list = $query->page($page, $limit)->order('id desc')->select()->toArray();

        // 追加 category_name 字段
        foreach ($list as &$item) {
            $item['category_name'] = $item['category']['name'] ?? '';
            unset($item['category']);
        }

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
     * 获取已发布的文章列表（C端，带分类名称）
     */
    public function getPublishedList(int $page = 1, int $limit = 10, int $categoryId = 0): array
    {
        $query = $this->model->with(['category'])
            ->where('status', '=', Article::STATUS_PUBLISHED)
            ->where('publish_at', '<=', date('Y-m-d H:i:s'));

        if ($categoryId > 0) {
            $query->where('category_id', '=', $categoryId);
        }

        $total = $query->count();
        $list = $query->page($page, $limit)->order('id desc')->select()->toArray();

        foreach ($list as &$item) {
            $item['category_name'] = $item['category']['name'] ?? '';
            $item['views'] = $item['view_count'] ?? 0;
            unset($item['category']);
        }

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
     * 递增浏览量
     */
    public function incrementViewCount(int $id): void
    {
        $this->inc(['id' => $id], 'view_count');
    }
}
