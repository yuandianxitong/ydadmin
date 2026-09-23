<?php

declare(strict_types=1);

namespace app\service\announcement;

use app\repository\announcement\AnnouncementRepository;
use core\base\Service;
use core\context\RequestContext;
use core\exception\NotFoundException;
use DI\Attribute\Inject;

/**
 * 公告。
 *
 * 只调 Repository：查询条件在 AnnouncementRepository。
 * 写操作一律包 runInTransaction()。created_by 不接收请求体；$dataScoped=false 时由本层写入。
 */
class AnnouncementService extends Service
{
    /** @var list<string> */
    private const WRITE_FIELDS = ['title', 'content', 'type', 'status', 'sort', 'publish_at'];

    #[Inject]
    protected AnnouncementRepository $announcementRepository;

    /**
     * 管理端列表：keyword、type、status。
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
     * C 端已发布列表：只认 status=PUBLISHED，不过滤 publish_at。
     *
     * @param array<string, mixed> $params
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getPublishedList(array $params, int $page, int $limit): array
    {
        return $this->announcementRepository->getPublishedList($params, $page, $limit);
    }

    /**
     * C 端详情：只认已发布。不存在 / 草稿 → 404。不加浏览量。
     *
     * @return array<string, mixed>
     */
    public function getPublishedDetail(int $id): array
    {
        $row = $this->announcementRepository->find($id);
        if ($row === null || (int) $row['status'] !== AnnouncementRepository::PUBLISHED) {
            throw new NotFoundException(lang('announcement.not_found'));
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $data 控制器 validate() 的返回值（字段白名单）
     * @return array<string, mixed>
     */
    public function createAnnouncement(array $data): array
    {
        $actor = RequestContext::actingUser();
        $row = $this->writable($data);
        $row['created_by'] = $actor > 0 ? $actor : null;
        $row = $this->fillPublishAt($row);

        return $this->runInTransaction(fn (): array => $this->announcementRepository->create($row));
    }

    /**
     * 局部更新：只写传了的字段，值为 null 的丢弃。草稿→发布且 publish_at 空则现填。
     *
     * @param array<string, mixed> $data 控制器 validate() 的返回值（字段白名单）
     */
    public function updateAnnouncement(int $id, array $data): void
    {
        $existing = $this->findAnnouncementOrFail($id);
        $update = array_filter(
            $this->writable($data),
            static fn ($value) => $value !== null
        );
        $update = $this->fillPublishAt($update, $existing);
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
        $existing = $this->findAnnouncementOrFail($id);
        $update = $this->fillPublishAt(['status' => $status], $existing);

        $this->runInTransaction(function () use ($id, $update): void {
            $this->announcementRepository->update($id, $update);
        });
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
        if ($status === AnnouncementRepository::PUBLISHED && empty($publishAt)) {
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
        $row = array_intersect_key($data, array_flip(self::WRITE_FIELDS));
        // 后台表单不选发布时间时发空串；datetime 列收空串在 strict 模式下是 1292，先转成 null。
        if (array_key_exists('publish_at', $row) && $row['publish_at'] === '') {
            $row['publish_at'] = null;
        }

        return $row;
    }

    /** @return array<string, mixed> */
    private function findAnnouncementOrFail(int $id): array
    {
        return $this->announcementRepository->find($id)
            ?? throw new NotFoundException(lang('announcement.not_found'));
    }
}
