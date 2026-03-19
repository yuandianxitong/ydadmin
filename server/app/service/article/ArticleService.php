<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\service\article;

use app\model\article\Article;
use app\repository\article\ArticleRepository;
use core\base\Service;

class ArticleService extends Service
{
    protected ArticleRepository $articleRepository;

    /**
     * 获取文章列表（管理端）
     */
    public function getArticleList(array $params): array
    {
        $page = (int) ($params['page_no'] ?? 1);
        $limit = (int) ($params['page_size'] ?? 20);
        return $this->articleRepository->getSearchList($params, $page, $limit);
    }

    /**
     * 获取文章详情（带分类名称）
     */
    public function getArticleDetail(int $id): ?array
    {
        $article = $this->articleRepository->findWithCategory($id);
        if (!$article) {
            $this->throwBusinessException(lang('business.record_not_found'));
        }

        // 递增浏览量
        $this->articleRepository->incrementViewCount($id);

        return $article;
    }

    /**
     * 创建文章
     */
    public function createArticle(array $data): array
    {
        // 如果状态为已发布，设置发布时间
        if (isset($data['status']) && (int) $data['status'] === Article::STATUS_PUBLISHED) {
            $data['publish_at'] = $data['publish_at'] ?? date('Y-m-d H:i:s');
        }

        $article = $this->articleRepository->create($data);

        $this->trigger('article.created', [
            'article_id' => $article['id'],
            'title'      => $data['title'],
        ]);

        return $article;
    }

    /**
     * 更新文章
     */
    public function updateArticle(int $id, array $data): bool
    {
        $article = $this->articleRepository->find($id);
        if (!$article) {
            $this->throwBusinessException(lang('business.record_not_found'));
        }

        // 如果从草稿变为已发布且没有发布时间，设置发布时间
        if (isset($data['status']) && (int) $data['status'] === Article::STATUS_PUBLISHED
            && (int) $article['status'] === Article::STATUS_DRAFT) {
            $data['publish_at'] = $data['publish_at'] ?? date('Y-m-d H:i:s');
        }

        return $this->articleRepository->update($id, $data);
    }

    /**
     * 删除文章（软删除）
     */
    public function deleteArticle(int $id): bool
    {
        $article = $this->articleRepository->find($id);
        if (!$article) {
            $this->throwBusinessException(lang('business.record_not_found'));
        }

        return $this->articleRepository->delete($id);
    }

    /**
     * 更新文章状态
     */
    public function updateStatus(int $id, int $status): bool
    {
        $article = $this->articleRepository->find($id);
        if (!$article) {
            $this->throwBusinessException(lang('business.record_not_found'));
        }

        $updateData = ['status' => $status];

        // 发布时设置发布时间
        if ($status === Article::STATUS_PUBLISHED && (int) $article['status'] === Article::STATUS_DRAFT) {
            $updateData['publish_at'] = date('Y-m-d H:i:s');
        }

        return $this->articleRepository->update($id, $updateData);
    }

    /**
     * 获取已发布的文章列表（C端）
     */
    public function getPublishedList(array $params): array
    {
        $page = (int) ($params['page_no'] ?? 1);
        $limit = (int) ($params['page_size'] ?? 20);
        $categoryId = (int) ($params['category_id'] ?? 0);
        return $this->articleRepository->getPublishedList($page, $limit, $categoryId);
    }
}
