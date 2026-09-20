<?php

declare(strict_types=1);

namespace app\service\announcement;

use app\repository\announcement\AnnouncementRepository;
use core\base\Service;
use core\exception\BusinessException;
use DI\Attribute\Inject;

/**
 * 公告（由代码生成器生成）。
 *
 * 只调 Repository：查询条件与数据权限都在 AnnouncementRepository 里，这一层不直接调用数据库门面。
 * 写操作一律包 runInTransaction()；缓存失效之类的副作用请在事务里用 afterCommit() 追加。
 */
class AnnouncementService extends Service
{
    #[Inject]
    protected AnnouncementRepository $announcementRepository;

    /**
     * 列表：keyword、区间等查询条件在 Repository 里按列类型展开。
     *
     * @param array<string, mixed> $params
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getAnnouncementList(array $params, int $page, int $limit): array
    {
        return $this->announcementRepository->getAnnouncementList($params, $page, $limit);
    }

    /** @return array<string, mixed> */
    public function getAnnouncementDetail(int $id): array
    {
        return $this->findAnnouncementOrFail($id);
    }

    /**
     * @param array<string, mixed> $data 控制器 validate() 的返回值（字段白名单）
     * @return array<string, mixed>
     */
    public function createAnnouncement(array $data): array
    {
        $row = array_intersect_key($data, array_flip(['title', 'content', 'type', 'status', 'sort', 'publish_at']));

        return $this->runInTransaction(fn (): array => $this->announcementRepository->create($row));
    }

    /**
     * 局部更新：只写传了的字段，值为 null 的丢弃；白名单与 create 一致。
     *
     * @param array<string, mixed> $data 控制器 validate() 的返回值（字段白名单）
     */
    public function updateAnnouncement(int $id, array $data): void
    {
        $this->findAnnouncementOrFail($id);
        $update = array_filter(
            array_intersect_key($data, array_flip(['title', 'content', 'type', 'status', 'sort', 'publish_at'])),
            static fn ($value) => $value !== null
        );
        if ($update === []) {
            return;
        }

        $this->runInTransaction(function () use ($id, $update): void {
            $this->announcementRepository->update($id, $update);
        });
    }

    public function deleteAnnouncement(int $id): void
    {
        $this->findAnnouncementOrFail($id);

        $this->runInTransaction(function () use ($id): void {
            $this->announcementRepository->delete($id);
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
                $this->deleteAnnouncement($id);
            }
        });
    }

    public function updateStatus(int $id, int $status): void
    {
        $this->findAnnouncementOrFail($id);

        $this->runInTransaction(function () use ($id, $status): void {
            $this->announcementRepository->update($id, ['status' => $status]);
        });
    }

    /** @return array<string, mixed> */
    private function findAnnouncementOrFail(int $id): array
    {
        return $this->announcementRepository->find($id) ?? throw new BusinessException(lang('announcement.announcement_not_found'));
    }
}
