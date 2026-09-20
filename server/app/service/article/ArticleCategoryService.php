<?php

declare(strict_types=1);

namespace app\service\article;

use app\repository\article\ArticleCategoryRepository;
use core\base\Service;
use core\exception\BusinessException;
use DI\Attribute\Inject;

/**
 * 文章栏目（由代码生成器生成）。
 *
 * 只调 Repository：查询条件与数据权限都在 ArticleCategoryRepository 里，这一层不直接调用数据库门面。
 * 写操作一律包 runInTransaction()；缓存失效之类的副作用请在事务里用 afterCommit() 追加。
 */
class ArticleCategoryService extends Service
{
    #[Inject]
    protected ArticleCategoryRepository $articleCategoryRepository;

    /**
     * 列表：keyword、区间等查询条件在 Repository 里按列类型展开。
     *
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function getArticleCategoryList(array $params): array
    {
        return $this->articleCategoryRepository->getArticleCategoryList($params);
    }

    /**
     * 下拉选项（本任务先返回已启用扁平行；exclude_id 留给后续任务）。
     *
     * @return list<array<string, mixed>>
     */
    public function getArticleCategoryOptions(): array
    {
        return $this->articleCategoryRepository->getEnabledOptions();
    }

    /** @return array<string, mixed> */
    public function getArticleCategoryDetail(int $id): array
    {
        return $this->findArticleCategoryOrFail($id);
    }

    /**
     * @param array<string, mixed> $data 控制器 validate() 的返回值（字段白名单）
     * @return array<string, mixed>
     */
    public function createArticleCategory(array $data): array
    {
        $row = array_intersect_key($data, array_flip(['parent_id', 'name', 'icon', 'sort', 'status']));

        return $this->runInTransaction(fn (): array => $this->articleCategoryRepository->create($row));
    }

    /**
     * 局部更新：只写传了的字段，值为 null 的丢弃；白名单与 create 一致。
     *
     * @param array<string, mixed> $data 控制器 validate() 的返回值（字段白名单）
     */
    public function updateArticleCategory(int $id, array $data): void
    {
        $this->findArticleCategoryOrFail($id);
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

    public function deleteArticleCategory(int $id): void
    {
        $this->findArticleCategoryOrFail($id);

        $this->runInTransaction(function () use ($id): void {
            $this->articleCategoryRepository->delete($id);
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
                $this->deleteArticleCategory($id);
            }
        });
    }

    public function updateStatus(int $id, int $status): void
    {
        $this->findArticleCategoryOrFail($id);

        $this->runInTransaction(function () use ($id, $status): void {
            $this->articleCategoryRepository->update($id, ['status' => $status]);
        });
    }

    /** @return array<string, mixed> */
    private function findArticleCategoryOrFail(int $id): array
    {
        return $this->articleCategoryRepository->find($id) ?? throw new BusinessException(lang('article_category.article_category_not_found'));
    }
}
