<?php

declare(strict_types=1);

namespace app\service\version;

use app\repository\version\AppVersionRepository;
use core\base\Service;
use core\exception\BusinessException;
use core\exception\NotFoundException;
use DI\Attribute\Inject;

/**
 * 应用版本（由代码生成器生成）。
 *
 * 只调 Repository：查询条件与数据权限都在 AppVersionRepository 里，这一层不直接调用数据库门面。
 * 写操作一律包 runInTransaction()；缓存失效之类的副作用请在事务里用 afterCommit() 追加。
 */
class AppVersionService extends Service
{
    #[Inject]
    protected AppVersionRepository $appVersionRepository;

    /**
     * 列表：keyword、区间等查询条件在 Repository 里按列类型展开。
     *
     * @param array<string, mixed> $params
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getAppVersionList(array $params, int $page, int $limit): array
    {
        return $this->appVersionRepository->getAppVersionList($params, $page, $limit);
    }

    /** @return array<string, mixed> */
    public function getAppVersionDetail(int $id): array
    {
        return $this->findAppVersionOrFail($id);
    }

    /**
     * @param array<string, mixed> $data 控制器 validate() 的返回值（字段白名单）
     * @return array<string, mixed>
     */
    public function createAppVersion(array $data): array
    {
        $row = array_intersect_key($data, array_flip(['platform', 'version', 'version_code', 'download_url', 'description', 'force_update', 'status']));

        return $this->runInTransaction(fn (): array => $this->appVersionRepository->create($row));
    }

    /**
     * 局部更新：只写传了的字段，值为 null 的丢弃；白名单与 create 一致。
     *
     * @param array<string, mixed> $data 控制器 validate() 的返回值（字段白名单）
     */
    public function updateAppVersion(int $id, array $data): void
    {
        $this->findAppVersionOrFail($id);
        $update = array_filter(
            array_intersect_key($data, array_flip(['platform', 'version', 'version_code', 'download_url', 'description', 'force_update', 'status'])),
            static fn ($value) => $value !== null
        );
        if ($update === []) {
            return;
        }

        $this->runInTransaction(function () use ($id, $update): void {
            $this->appVersionRepository->update($id, $update);
        });
    }

    public function deleteAppVersion(int $id): void
    {
        $this->findAppVersionOrFail($id);

        $this->runInTransaction(function () use ($id): void {
            $this->appVersionRepository->delete($id);
        });
    }

    /**
     * C 端检查更新：取平台最新启用版本，与当前 version_code 比较。
     *
     * @return array{need_update: bool, force_update: bool, version?: mixed, version_code?: mixed, download_url?: mixed, description?: mixed}
     */
    public function checkUpdate(string $platform, int $versionCode): array
    {
        if ($platform === '' || $versionCode <= 0) {
            throw new BusinessException(lang('version.check_params'));
        }

        $latest = $this->appVersionRepository->getLatestEnabled($platform);
        if ($latest === null || (int) $latest['version_code'] <= $versionCode) {
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

    /** @return array<string, mixed> */
    private function findAppVersionOrFail(int $id): array
    {
        return $this->appVersionRepository->find($id) ?? throw new NotFoundException(lang('version.not_found'));
    }
}
