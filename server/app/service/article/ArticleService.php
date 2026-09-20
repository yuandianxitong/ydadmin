<?php

declare(strict_types=1);

namespace app\service\article;

use app\repository\article\ArticleCategoryRepository;
use app\repository\article\ArticleRepository;
use core\base\Service;
use core\context\RequestContext;
use core\exception\BusinessException;
use core\exception\NotFoundException;
use DI\Attribute\Inject;

/**
 * 文章。
 *
 * 只调 Repository：查询条件在 ArticleRepository，栏目存在性走 ArticleCategoryRepository。
 * 写操作一律包 runInTransaction()。created_by / view_count 不接收请求体。
 */
class ArticleService extends Service
{
    /** @var list<string> */
    private const WRITE_FIELDS = ['category_id', 'title', 'cover', 'summary', 'content', 'tags', 'author', 'status', 'publish_at'];

    #[Inject]
    protected ArticleRepository $articleRepository;

    #[Inject]
    protected ArticleCategoryRepository $articleCategoryRepository;

    /**
     * 管理端列表：keyword、category_id、status。
     *
     * @param array<string, mixed> $params
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getArticleList(array $params, int $page, int $limit): array
    {
        return $this->articleRepository->getAdminList($params, $page, $limit);
    }

    /** @return array<string, mixed> */
    public function getArticleDetail(int $id): array
    {
        return $this->articleRepository->findWithCategory($id)
            ?? throw new NotFoundException(lang('article.not_found'));
    }

    /**
     * C 端已发布列表：只认 status=PUBLISHED，不过滤 publish_at。
     *
     * @param array<string, mixed> $params
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getPublishedList(array $params, int $page, int $limit): array
    {
        return $this->articleRepository->getPublishedList($params, $page, $limit);
    }

    /**
     * C 端详情：先确认已发布，再自增阅读量，再带栏目名与 views。
     *
     * @return array<string, mixed>
     */
    public function getPublishedDetail(int $id): array
    {
        $row = $this->articleRepository->find($id);
        if ($row === null || (int) $row['status'] !== ArticleRepository::PUBLISHED) {
            throw new NotFoundException(lang('article.not_found'));
        }

        $this->articleRepository->incrementViewCount($id);

        return $this->articleRepository->findWithCategory($id, true)
            ?? throw new NotFoundException(lang('article.not_found'));
    }

    /**
     * @param array<string, mixed> $data 控制器 validate() 的返回值（字段白名单）
     * @return array<string, mixed>
     */
    public function createArticle(array $data): array
    {
        $this->assertCategoryExists((int) $data['category_id']);

        $actor = RequestContext::actingUser();
        $row = $this->writable($data);
        $row['created_by'] = $actor > 0 ? $actor : null;
        $row['view_count'] = 0;
        $row = $this->fillPublishAt($row);

        return $this->runInTransaction(fn (): array => $this->articleRepository->create($row));
    }

    /**
     * 局部更新：只写传了的字段，值为 null 的丢弃。草稿→发布且 publish_at 空则现填。
     *
     * @param array<string, mixed> $data 控制器 validate() 的返回值（字段白名单）
     */
    public function updateArticle(int $id, array $data): void
    {
        $existing = $this->findArticleOrFail($id);
        if (array_key_exists('category_id', $data)) {
            $this->assertCategoryExists((int) $data['category_id']);
        }

        $update = array_filter(
            $this->writable($data),
            static fn ($value) => $value !== null
        );
        $update = $this->fillPublishAt($update, $existing);
        if ($update === []) {
            return;
        }

        $this->runInTransaction(function () use ($id, $update): void {
            $this->articleRepository->update($id, $update);
        });
    }

    public function deleteArticle(int $id): void
    {
        $this->findArticleOrFail($id);

        $this->runInTransaction(function () use ($id): void {
            $this->articleRepository->delete($id);
        });
    }

    /**
     * 批量删除：同一事务内逐条删除，任一 id 不存在则整体回滚（与 M1 的角色、字典批量删除同语义）。
     * 重复 id 先去重。
     *
     * @param list<int> $ids
     */
    public function batchDelete(array $ids): void
    {
        $this->runInTransaction(function () use ($ids): void {
            foreach (array_values(array_unique($ids)) as $id) {
                $this->deleteArticle($id);
            }
        });
    }

    public function updateStatus(int $id, int $status): void
    {
        $existing = $this->findArticleOrFail($id);
        $update = $this->fillPublishAt(['status' => $status], $existing);

        $this->runInTransaction(function () use ($id, $update): void {
            $this->articleRepository->update($id, $update);
        });
    }

    private function assertCategoryExists(int $categoryId): void
    {
        if ($this->articleCategoryRepository->find($categoryId) === null) {
            throw new BusinessException(lang('article.category_invalid'));
        }
    }

    /**
     * status===PUBLISHED 且 publish_at 空则现填；不覆盖已有发布时间。
     *
     * @param array<string, mixed> $row
     * @param array<string, mixed>|null $existing
     * @return array<string, mixed>
     */
    private function fillPublishAt(array $row, ?array $existing = null): array
    {
        $status = array_key_exists('status', $row)
            ? (int) $row['status']
            : (int) ($existing['status'] ?? 0);
        $publishAt = array_key_exists('publish_at', $row)
            ? $row['publish_at']
            : ($existing['publish_at'] ?? null);
        if ($status === ArticleRepository::PUBLISHED && empty($publishAt)) {
            $row['publish_at'] = date('Y-m-d H:i:s');
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function writable(array $data): array
    {
        return array_intersect_key($data, array_flip(self::WRITE_FIELDS));
    }

    /** @return array<string, mixed> */
    private function findArticleOrFail(int $id): array
    {
        return $this->articleRepository->find($id)
            ?? throw new NotFoundException(lang('article.not_found'));
    }
}
