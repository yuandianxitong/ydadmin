<?php

declare(strict_types=1);

namespace app\service\article;

use app\repository\article\ArticleCategoryRepository;
use app\repository\article\ArticleRepository;
use core\base\Service;
use core\exception\BusinessException;
use core\exception\NotFoundException;
use DI\Attribute\Inject;

/**
 * 文章栏目。
 *
 * 只调 Repository：查询条件在 ArticleCategoryRepository，文章占用计数走 ArticleRepository。
 * 写操作一律包 runInTransaction()。
 */
class ArticleCategoryService extends Service
{
    #[Inject]
    protected ArticleCategoryRepository $articleCategoryRepository;

    #[Inject]
    protected ArticleRepository $articleRepository;

    /**
     * 管理端树：先按 keyword（name）/status 滤扁平行再组树。
     *
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function getTree(array $params): array
    {
        $keyword = trim((string) ($params['keyword'] ?? ''));
        $status = $params['status'] ?? null;

        return $this->articleCategoryRepository->getTree($keyword === '' ? null : $keyword, $status);
    }

    /**
     * 下拉树：仅启用；exclude_id 去掉自身及子孙。
     *
     * @return list<array<string, mixed>>
     */
    public function getOptions(int $excludeId = 0): array
    {
        return $this->articleCategoryRepository->getOptions($excludeId);
    }

    /** @return array<string, mixed> */
    public function getDetail(int $id): array
    {
        return $this->findOrFail($id);
    }

    /**
     * @param array<string, mixed> $data 控制器 validate() 的返回值（字段白名单）
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        $this->assertNameUnique((string) $data['name']);
        $parentId = (int) ($data['parent_id'] ?? 0);
        $this->assertParentValid($parentId);

        $row = [
            'parent_id' => $parentId,
            'name'      => $data['name'],
            'icon'      => $data['icon'] ?? '',
            'sort'      => $data['sort'] ?? 0,
            'status'    => $data['status'] ?? 1,
        ];

        return $this->runInTransaction(fn (): array => $this->articleCategoryRepository->create($row));
    }

    /**
     * 局部更新：只写传了的字段，值为 null 的丢弃。
     *
     * @param array<string, mixed> $data 控制器 validate() 的返回值（字段白名单）
     */
    public function update(int $id, array $data): void
    {
        $this->findOrFail($id);
        if (isset($data['name'])) {
            $this->assertNameUnique((string) $data['name'], $id);
        }
        if (array_key_exists('parent_id', $data)) {
            $this->assertParentValid((int) $data['parent_id'], $id);
        }

        $update = array_filter(
            array_intersect_key($data, array_flip(['parent_id', 'name', 'icon', 'sort', 'status'])),
            static fn ($value) => $value !== null
        );
        if ($update === []) {
            return;
        }

        $this->runInTransaction(function () use ($id, $update): void {
            $this->articleCategoryRepository->update($id, $update);
        });
    }

    public function delete(int $id): void
    {
        $this->findOrFail($id);

        if ($this->articleCategoryRepository->hasChildren($id)) {
            throw new BusinessException(lang('article_category.has_children'));
        }
        if ($this->articleRepository->countByCategoryId($id) > 0) {
            throw new BusinessException(lang('article_category.has_articles'));
        }

        $this->runInTransaction(function () use ($id): void {
            $this->articleCategoryRepository->delete($id);
        });
    }

    /**
     * 批量删除：同一事务内逐条删除，任一 id 不存在则整体回滚。
     *
     * @param list<int> $ids
     */
    public function batchDelete(array $ids): void
    {
        $this->runInTransaction(function () use ($ids): void {
            foreach (array_values(array_unique($ids)) as $id) {
                $this->delete($id);
            }
        });
    }

    public function updateStatus(int $id, int $status): void
    {
        $this->findOrFail($id);

        $this->runInTransaction(function () use ($id, $status): void {
            $this->articleCategoryRepository->update($id, ['status' => $status]);
        });
    }

    private function assertNameUnique(string $name, int $excludeId = 0): void
    {
        if ($this->articleCategoryRepository->existsName($name, $excludeId)) {
            throw new BusinessException(lang('article_category.name_exists'));
        }
    }

    private function assertParentValid(int $parentId, int $id = 0): void
    {
        if ($parentId <= 0) {
            return;
        }
        if ($id > 0 && $parentId === $id) {
            throw new BusinessException(lang('article_category.parent_invalid'));
        }
        if ($this->articleCategoryRepository->find($parentId) === null) {
            throw new BusinessException(lang('article_category.parent_invalid'));
        }
        if ($id > 0 && in_array($parentId, $this->articleCategoryRepository->descendantIds($id), true)) {
            throw new BusinessException(lang('article_category.parent_invalid'));
        }
    }

    /** @return array<string, mixed> */
    private function findOrFail(int $id): array
    {
        return $this->articleCategoryRepository->find($id)
            ?? throw new NotFoundException(lang('article_category.not_found'));
    }
}
