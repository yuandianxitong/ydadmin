<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\service\version;

use app\repository\version\AppVersionRepository;
use core\base\Service;

class AppVersionService extends Service
{
    protected AppVersionRepository $appVersionRepository;

    /**
     * 搜索版本列表（管理端）
     */
    public function getList(array $params): array
    {
        [$page, $limit] = $this->extractPagination($params);
        return $this->appVersionRepository->getSearchList($params, $page, $limit);
    }

    /**
     * 版本详情
     */
    public function detail(int $id): ?array
    {
        return $this->appVersionRepository->find($id);
    }

    /**
     * 创建版本
     */
    public function create(array $data): array
    {
        return $this->appVersionRepository->create($data);
    }

    /**
     * 更新版本
     */
    public function update(int $id, array $data): bool
    {
        return $this->appVersionRepository->update($id, $data);
    }

    /**
     * 删除版本
     */
    public function delete(int $id): bool
    {
        return $this->appVersionRepository->delete($id);
    }

    /**
     * 检查更新
     * 查找指定平台中 version_code 大于给定值的最新启用版本
     */
    public function checkUpdate(string $platform, int $versionCode): array
    {
        $latest = $this->appVersionRepository->getLatestVersion($platform);

        if (!$latest || $latest['version_code'] <= $versionCode) {
            return [
                'need_update'  => false,
                'force_update' => false,
            ];
        }

        return [
            'need_update'  => true,
            'force_update' => (bool) $latest['force_update'],
            'version'      => $latest['version'],
            'version_code' => $latest['version_code'],
            'download_url' => $latest['download_url'],
            'description'  => $latest['description'],
        ];
    }
}
