<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\service\diy;

use app\repository\diy\DiyPageRepository;
use core\base\Service;
use core\diy\DiyWidgetRegistry;
use core\exception\BusinessException;

class DiyPageService extends Service
{
    protected DiyPageRepository $repo;

    private const SLUG_RE = '/^[a-z0-9][a-z0-9-]{0,62}[a-z0-9]$/';

    private const SYSTEM_PAGES = [
        'home'   => ['page_type' => 'home', 'title' => '首页'],
        'member' => ['page_type' => 'member', 'title' => '个人中心'],
    ];

    public function getDraft(string $key): array
    {
        $row = $this->repo->findByKey($key);

        return [
            'components'    => $this->normalizeComponents($row['components_draft'] ?? []),
            'page_settings' => $this->normalizeSettings($row['page_settings'] ?? []),
        ];
    }

    public function saveDraft(string $key, array $components, array $pageSettings): void
    {
        $clean = (new DiyWidgetRegistry())->validate($components);
        $now   = date('Y-m-d H:i:s');
        $row   = $this->repo->findByKey($key);

        if ($row === null) {
            if (!isset(self::SYSTEM_PAGES[$key])) {
                throw new BusinessException('页面不存在', 404);
            }
            $this->repo->create([
                'page_type'        => self::SYSTEM_PAGES[$key]['page_type'],
                'page_key'         => $key,
                'platform'         => 'uniapp',
                'title'            => self::SYSTEM_PAGES[$key]['title'],
                'components_draft' => $clean,
                'page_settings'    => $pageSettings,
                'status'           => 1,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);

            return;
        }

        // Model 已对 JSON 字段做 cast，此处必须传数组，禁止再 json_encode（否则二次编码导致摘要/发布异常）
        $this->repo->update((int) $row['id'], [
            'components_draft' => $clean,
            'page_settings'    => $pageSettings,
            'updated_at'       => $now,
        ]);
    }

    public function publish(string $key, int $createdBy = 0): void
    {
        $this->runInTransaction(function () use ($key, $createdBy): void {
            $row = $this->repo->findByKey($key);
            if ($row === null) {
                throw new BusinessException('请先保存草稿再发布', 422);
            }

            // 治愈历史二次编码数据后再发布
            $draft = $this->normalizeComponents($row['components_draft'] ?? []);
            $settings = $this->normalizeSettings($row['page_settings'] ?? []);
            if ($draft !== ($row['components_draft'] ?? null) || $settings !== ($row['page_settings'] ?? null)) {
                $this->repo->update((int) $row['id'], [
                    'components_draft' => $draft,
                    'page_settings'    => $settings,
                    'updated_at'       => date('Y-m-d H:i:s'),
                ]);
            }

            // 不根据 affected rows 判断成败：草稿与已发布相同时 MySQL 可能返回 0
            $this->repo->publishByKey($key);
            $row = $this->repo->findByKey($key);
            if ($row === null) {
                throw new BusinessException('发布失败：页面不存在', 404);
            }
            $published = $this->normalizeComponents($row['components_published'] ?? []);
            $this->repo->insertVersion(
                (int) $row['id'],
                $published,
                $this->normalizeSettings($row['page_settings'] ?? []),
                $createdBy
            );
        });
    }

    public function listPageVersions(string $key): array
    {
        $row = $this->repo->findByKey($key);
        return $row === null ? [] : $this->repo->listVersions((int) $row['id']);
    }

    public function restorePageVersion(string $key, int $versionId): void
    {
        $page = $this->repo->findByKey($key);
        if ($page === null) {
            throw new BusinessException('页面不存在', 404);
        }
        $ver = $this->repo->findVersion($versionId);
        if ($ver === null || (int) $ver['page_id'] !== (int) $page['id']) {
            throw new BusinessException('版本不存在', 404);
        }
        $this->repo->restoreDraft((int) $page['id'], $ver['components'] ?? [], $ver['page_settings'] ?? []);
    }

    /** C 端：取已发布页；未发布/禁用/不存在返回 null。 */
    public function getPublished(string $key, string $platform = 'uniapp'): ?array
    {
        $row = $this->repo->findByKey($key, $platform);
        if ($row === null || (int) ($row['status'] ?? 0) !== 1) {
            return null;
        }
        $components = $this->normalizeComponents($row['components_published'] ?? []);
        if ($components === []) {
            return null;
        }

        return [
            'title'         => (string) ($row['title'] ?? ''),
            'components'    => $this->filterBuiltinComponents($components),
            'page_settings' => $this->normalizeSettings($row['page_settings'] ?? []),
        ];
    }

    /** @param array<int,mixed> $components */
    private function filterBuiltinComponents(array $components): array
    {
        $allowed = DiyWidgetRegistry::TYPES;
        $out = [];
        foreach ($components as $c) {
            if (!is_array($c)) {
                continue;
            }
            $type = (string) ($c['type'] ?? '');
            if (!in_array($type, $allowed, true)) {
                continue;
            }
            $out[] = $c;
        }
        return $out;
    }

    /**
     * 将组件树规范为 list；兼容历史二次 json_encode 存成的 JSON 字符串。
     *
     * @param mixed $raw
     * @return array<int,array<string,mixed>>
     */
    private function normalizeComponents(mixed $raw): array
    {
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            return [];
        }
        // 关联数组且无组件形态时视为空
        if ($raw !== [] && !$this->isListArray($raw)) {
            // 可能是 {"0": {...}} 形式
            $raw = array_values($raw);
        }
        $out = [];
        foreach ($raw as $c) {
            if (is_string($c)) {
                $decoded = json_decode($c, true);
                if (is_array($decoded)) {
                    $c = $decoded;
                }
            }
            if (is_array($c) && ($c['id'] ?? '') !== '' && ($c['type'] ?? '') !== '') {
                $out[] = $c;
            }
        }
        return $out;
    }

    /** @param mixed $raw @return array<string,mixed> */
    private function normalizeSettings(mixed $raw): array
    {
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($raw) ? $raw : [];
    }

    /** @param array<mixed> $arr */
    private function isListArray(array $arr): bool
    {
        $i = 0;
        foreach ($arr as $k => $_) {
            if ($k !== $i) {
                return false;
            }
            $i++;
        }
        return true;
    }

    /** @return array{list: array<int,array<string,mixed>>, total: int} */
    public function listPages(int $page = 1, int $limit = 10, string $keyword = '', ?bool $published = null): array
    {
        return $this->repo->listPages(max(1, $page), max(1, min(100, $limit)), trim($keyword), $published);
    }

    public function copyPage(int $id): int
    {
        $src = $this->guardCustom($id);

        $draft = $this->normalizeComponents($src['components_draft'] ?? []);
        if ($draft === []) {
            $draft = $this->normalizeComponents($src['components_published'] ?? []);
        }

        $now = date('Y-m-d H:i:s');
        $row = $this->repo->create([
            'page_type'        => 'custom',
            'page_key'         => $this->nextCopyKey((string) $src['page_key']),
            'platform'         => 'uniapp',
            'title'            => mb_substr((string) $src['title'], 0, 90) . '-副本',
            'components_draft' => $draft,
            'page_settings'    => $src['page_settings'] ?? [],
            'status'           => (int) ($src['status'] ?? 1),
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);

        return (int) ($row['id'] ?? 0);
    }

    private function nextCopyKey(string $sourceKey): string
    {
        $base = rtrim(mb_substr($sourceKey, 0, 64 - 7), '-');
        for ($i = 1; $i <= 99; $i++) {
            $key = $base . '-copy' . ($i === 1 ? '' : $i);
            if (!$this->repo->existsKey($key)) {
                return $key;
            }
        }
        throw new BusinessException('副本过多，请先清理', 422);
    }

    public function createPage(string $title, string $key): int
    {
        $this->assertSlug($key);
        if ($this->repo->existsKey($key)) {
            throw new BusinessException('页面标识已存在', 409);
        }
        $now = date('Y-m-d H:i:s');

        $row = $this->repo->create([
            'page_type'        => 'custom',
            'page_key'         => $key,
            'platform'         => 'uniapp',
            'title'            => $title !== '' ? $title : $key,
            'components_draft' => [],
            'status'           => 1,
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);

        return (int) ($row['id'] ?? 0);
    }

    public function renamePage(int $id, string $title): void
    {
        $this->guardCustom($id);
        $this->repo->renamePage($id, $title);
    }

    public function updateSlug(int $id, string $key): void
    {
        $row = $this->guardCustom($id);
        $this->assertSlug($key);
        if ($key !== $row['page_key'] && $this->repo->existsKey($key)) {
            throw new BusinessException('页面标识已存在', 409);
        }
        $this->repo->updateSlug($id, $key);
    }

    public function setStatus(int $id, int $status): void
    {
        $this->guardCustom($id);
        $this->repo->setStatus($id, $status === 1 ? 1 : 0);
    }

    public function deletePage(int $id): void
    {
        $this->guardCustom($id);
        $this->repo->softDelete($id);
    }

    public function getHomeDraft(): array
    {
        return $this->getDraft('home');
    }

    public function saveHomeDraft(array $components, array $pageSettings): void
    {
        $this->saveDraft('home', $components, $pageSettings);
    }

    public function publishHome(int $createdBy = 0): void
    {
        $this->publish('home', $createdBy);
    }

    public function listHomeVersions(): array
    {
        return $this->listPageVersions('home');
    }

    public function restoreHomeVersion(int $versionId): void
    {
        $this->restorePageVersion('home', $versionId);
    }

    public function getPublishedHome(): ?array
    {
        return $this->getPublished('home');
    }

    public function getHomeSummary(): array
    {
        return $this->getPageSummary('home');
    }

    public function getPageSummary(string $key): array
    {
        if (!isset(self::SYSTEM_PAGES[$key])) {
            throw new BusinessException('仅支持系统页面摘要', 422);
        }
        $row = $this->repo->findByKey($key);
        if ($row === null) {
            return [
                'title'           => self::SYSTEM_PAGES[$key]['title'],
                'published'       => false,
                'component_count' => 0,
                'updated_at'      => null,
            ];
        }

        $draft = $this->normalizeComponents($row['components_draft'] ?? []);
        $published = $this->normalizeComponents($row['components_published'] ?? []);
        // 列表展示草稿组件数（编辑中的真实数量）；无草稿时回退已发布
        $count = count($draft) > 0 ? count($draft) : count($published);

        return [
            'title'           => (string) ($row['title'] ?? self::SYSTEM_PAGES[$key]['title']),
            'published'       => count($published) > 0,
            'component_count' => $count,
            'updated_at'      => $row['updated_at'] ?? null,
        ];
    }

    private function assertSlug(string $key): void
    {
        if (isset(self::SYSTEM_PAGES[$key]) || !preg_match(self::SLUG_RE, $key)) {
            throw new BusinessException('页面标识不合法（小写字母/数字/连字符，2-64位，不可为系统保留标识）', 422);
        }
    }

    private function guardCustom(int $id): array
    {
        $row = $this->repo->find($id);
        if ($row === null || ($row['page_type'] ?? '') !== 'custom') {
            throw new BusinessException('页面不存在', 404);
        }
        return $row;
    }
}
