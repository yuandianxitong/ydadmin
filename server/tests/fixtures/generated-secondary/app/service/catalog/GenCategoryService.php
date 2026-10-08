<?php

declare(strict_types=1);

namespace app\service\catalog;

use app\repository\catalog\GenCategoryRepository;
use core\base\Service;
use core\exception\BusinessException;
use DI\Attribute\Inject;

/**
 * 生成器夹具表二（无状态列/图片列/创建人列）（由代码生成器生成）。
 *
 * 只调 Repository：查询条件与数据权限都在 GenCategoryRepository 里，这一层不直接调用数据库门面。
 * 写操作一律包 runInTransaction()；缓存失效之类的副作用请在事务里用 afterCommit() 追加。
 */
class GenCategoryService extends Service
{
    #[Inject]
    protected GenCategoryRepository $genCategoryRepository;

    /**
     * 列表：keyword、区间等查询条件在 Repository 里按列类型展开。
     *
     * @param array<string, mixed> $params
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getGenCategoryList(array $params, int $page, int $limit): array
    {
        return $this->genCategoryRepository->getGenCategoryList($params, $page, $limit);
    }

    /** @return array<string, mixed> */
    public function getGenCategoryDetail(int $id): array
    {
        return $this->findGenCategoryOrFail($id);
    }

    /**
     * @param array<string, mixed> $data 控制器 validate() 的返回值（字段白名单）
     * @return array<string, mixed>
     */
    public function createGenCategory(array $data): array
    {
        $row = array_intersect_key($data, array_flip(['name', 'is_featured', 'settings', 'contact_email', 'sort']));

        return $this->runInTransaction(fn (): array => $this->genCategoryRepository->create($row));
    }

    /**
     * 局部更新：只写传了的字段，值为 null 的丢弃；白名单与 create 一致。
     *
     * @param array<string, mixed> $data 控制器 validate() 的返回值（字段白名单）
     */
    public function updateGenCategory(int $id, array $data): void
    {
        $this->findGenCategoryOrFail($id);
        $update = array_filter(
            array_intersect_key($data, array_flip(['name', 'is_featured', 'settings', 'contact_email', 'sort'])),
            static fn ($value) => $value !== null
        );
        if ($update === []) {
            return;
        }

        $this->runInTransaction(function () use ($id, $update): void {
            $this->genCategoryRepository->update($id, $update);
        });
    }

    public function deleteGenCategory(int $id): void
    {
        $this->findGenCategoryOrFail($id);

        $this->runInTransaction(function () use ($id): void {
            $this->genCategoryRepository->delete($id);
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
                $this->deleteGenCategory($id);
            }
        });
    }

    /** @return array<string, mixed> */
    private function findGenCategoryOrFail(int $id): array
    {
        return $this->genCategoryRepository->find($id) ?? throw new BusinessException(lang('catalog/gen_category.not_found'));
    }
}
