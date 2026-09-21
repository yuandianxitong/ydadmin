<?php

declare(strict_types=1);

namespace app\service\region;

use app\repository\region\RegionRepository;
use core\base\Service;
use core\exception\BusinessException;
use DI\Attribute\Inject;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * 地区（由代码生成器生成）。
 *
 * 只调 Repository：查询条件与数据权限都在 RegionRepository 里，这一层不直接调用数据库门面。
 * 写操作一律包 runInTransaction()；缓存失效之类的副作用请在事务里用 afterCommit() 追加。
 *
 * 唯一性（code）：写入前调 RegionRepository 的 existsBy* 预检查，
 * 命中就抛业务提示；更新时把自己这一行用 $excludeId 排除掉。并发写入撞上唯一索引时捕获
 * UniqueConstraintViolationException 转成同一条业务错误，不返回 500。
 */
class RegionService extends Service
{
    #[Inject]
    protected RegionRepository $regionRepository;

    /**
     * 列表：keyword、区间等查询条件在 Repository 里按列类型展开。
     *
     * @param array<string, mixed> $params
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getRegionList(array $params, int $page, int $limit): array
    {
        return $this->regionRepository->getRegionList($params, $page, $limit);
    }

    /** @return array<string, mixed> */
    public function getRegionDetail(int $id): array
    {
        return $this->findRegionOrFail($id);
    }

    /**
     * @param array<string, mixed> $data 控制器 validate() 的返回值（字段白名单）
     * @return array<string, mixed>
     */
    public function createRegion(array $data): array
    {
        $row = array_intersect_key($data, array_flip(['parent_id', 'name', 'code', 'level', 'sort', 'status']));
        if (isset($row['code']) && $this->regionRepository->existsByCode((string) $row['code'])) {
            throw new BusinessException(lang('region.region_code_exists'));
        }

        try {
            return $this->runInTransaction(fn (): array => $this->regionRepository->create($row));
        } catch (UniqueConstraintViolationException) {
            throw new BusinessException(lang('region.region_code_exists'));
        }
    }

    /**
     * 局部更新：只写传了的字段，值为 null 的丢弃；白名单与 create 一致。
     *
     * @param array<string, mixed> $data 控制器 validate() 的返回值（字段白名单）
     */
    public function updateRegion(int $id, array $data): void
    {
        $this->findRegionOrFail($id);
        $update = array_filter(
            array_intersect_key($data, array_flip(['parent_id', 'name', 'code', 'level', 'sort', 'status'])),
            static fn ($value) => $value !== null
        );
        if ($update === []) {
            return;
        }
        // 自己这一行由 $excludeId 排除，不必先比较值有没有改动：那次字符串比较依赖 find() 的返回值，
        // 而 find() 受数据权限约束，取不到该列时 ?? '' 会把「没改」误判成「改了」，白跑一次查重。
        if (isset($update['code']) && $this->regionRepository->existsByCode((string) $update['code'], $id)) {
            throw new BusinessException(lang('region.region_code_exists'));
        }

        try {
            $this->runInTransaction(function () use ($id, $update): void {
                $this->regionRepository->update($id, $update);
            });
        } catch (UniqueConstraintViolationException) {
            throw new BusinessException(lang('region.region_code_exists'));
        }
    }

    public function deleteRegion(int $id): void
    {
        $this->findRegionOrFail($id);

        $this->runInTransaction(function () use ($id): void {
            $this->regionRepository->delete($id);
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
                $this->deleteRegion($id);
            }
        });
    }

    public function updateStatus(int $id, int $status): void
    {
        $this->findRegionOrFail($id);

        $this->runInTransaction(function () use ($id, $status): void {
            $this->regionRepository->update($id, ['status' => $status]);
        });
    }

    /** @return array<string, mixed> */
    private function findRegionOrFail(int $id): array
    {
        return $this->regionRepository->find($id) ?? throw new BusinessException(lang('region.region_not_found'));
    }
}
