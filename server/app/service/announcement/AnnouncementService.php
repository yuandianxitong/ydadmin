<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\service\announcement;

use app\model\announcement\Announcement;
use app\repository\announcement\AnnouncementRepository;
use core\base\Service;
use core\exception\BusinessException;

class AnnouncementService extends Service
{
    protected AnnouncementRepository $announcementRepository;

    /**
     * 获取公告列表（管理端）
     */
    public function getList(array $params): array
    {
        $page = (int) ($params['page_no'] ?? 1);
        $limit = (int) ($params['page_size'] ?? 20);
        return $this->announcementRepository->getSearchList($params, $page, $limit);
    }

    /**
     * 公告详情
     */
    public function detail(int $id): ?array
    {
        return $this->announcementRepository->find($id);
    }

    /**
     * 创建公告
     */
    public function create(array $data): array
    {
        // 如果状态为已发布，设置发布时间
        if (isset($data['status']) && (int) $data['status'] === Announcement::STATUS_PUBLISHED) {
            $data['publish_at'] = date('Y-m-d H:i:s');
        }

        $announcement = $this->announcementRepository->create($data);

        $this->trigger('announcement.created', [
            'announcement_id' => $announcement['id'],
            'title'           => $data['title'],
        ]);

        return $announcement;
    }

    /**
     * 更新公告
     */
    public function update(int $id, array $data): bool
    {
        $announcement = $this->announcementRepository->find($id);
        if (!$announcement) {
            throw new BusinessException(lang('business.record_not_found'));
        }

        // 如果从草稿变为已发布且没有发布时间，设置发布时间
        if (isset($data['status']) && (int) $data['status'] === Announcement::STATUS_PUBLISHED
            && (int) $announcement['status'] === Announcement::STATUS_DRAFT) {
            $data['publish_at'] = date('Y-m-d H:i:s');
        }

        return $this->announcementRepository->update($id, $data);
    }

    /**
     * 更新公告状态
     */
    public function updateStatus(int $id, int $status): bool
    {
        $announcement = $this->announcementRepository->find($id);
        if (!$announcement) {
            throw new BusinessException(lang('business.record_not_found'));
        }

        $updateData = ['status' => $status];

        // 发布时设置发布时间
        if ($status === Announcement::STATUS_PUBLISHED && (int) $announcement['status'] === Announcement::STATUS_DRAFT) {
            $updateData['publish_at'] = date('Y-m-d H:i:s');
        }

        return $this->announcementRepository->update($id, $updateData);
    }

    /**
     * 获取已发布的公告列表（C端）
     */
    public function getPublishedList(array $params): array
    {
        $page = (int) ($params['page_no'] ?? 1);
        $limit = (int) ($params['page_size'] ?? 20);
        return $this->announcementRepository->getPublishedList($page, $limit);
    }

    /**
     * 删除公告
     */
    public function delete(int $id): bool
    {
        return $this->announcementRepository->delete($id);
    }
}
