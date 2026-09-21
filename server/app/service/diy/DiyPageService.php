<?php

declare(strict_types=1);

namespace app\service\diy;

use app\repository\diy\DiyPageRepository;
use app\repository\diy\DiyPageVersionRepository;
use core\base\Service;
use core\context\RequestContext;
use core\diy\DiyWidgetRegistry;
use core\exception\BusinessException;
use core\exception\NotFoundException;
use DI\Attribute\Inject;

/**
 * 装修页面：系统页（home/member）草稿读写、发布插版本、回滚只改草稿、C 端已发布。
 *
 * 只调 Repository。自定义页 CRUD 由后续任务补。
 */
class DiyPageService extends Service
{
    /** @var array<string, array{page_type: string, title: string}> */
    private const SYSTEM_PAGES = [
        'home'   => ['page_type' => 'home', 'title' => '首页'],
        'member' => ['page_type' => 'member', 'title' => '个人中心'],
    ];

    #[Inject]
    protected DiyPageRepository $diyPageRepository;

    #[Inject]
    protected DiyPageVersionRepository $diyPageVersionRepository;

    #[Inject]
    protected DiyWidgetRegistry $widgetRegistry;

    /**
     * @return array{components: list<array<string, mixed>>, page_settings: array<string, mixed>}
     */
    public function getDraft(string $key): array
    {
        $row = $this->diyPageRepository->findByKey($key);
        if ($row === null) {
            if (!isset(self::SYSTEM_PAGES[$key])) {
                throw new NotFoundException();
            }

            return ['components' => [], 'page_settings' => []];
        }

        return [
            'components'    => $this->normalizeComponents($row['components_draft'] ?? []),
            'page_settings' => $this->normalizeSettings($row['page_settings'] ?? []),
        ];
    }

    /**
     * @param array<int, mixed> $components
     * @param array<string, mixed> $pageSettings
     */
    public function saveDraft(string $key, array $components, array $pageSettings): void
    {
        $clean = $this->widgetRegistry->validate(array_values($components));
        $row = $this->diyPageRepository->findByKey($key);

        if ($row === null) {
            if (!isset(self::SYSTEM_PAGES[$key])) {
                throw new NotFoundException();
            }
            $this->diyPageRepository->create([
                'page_type'        => self::SYSTEM_PAGES[$key]['page_type'],
                'page_key'         => $key,
                'platform'         => 'uniapp',
                'title'            => self::SYSTEM_PAGES[$key]['title'],
                'components_draft' => $clean,
                'page_settings'    => $pageSettings,
                'status'           => 1,
            ]);

            return;
        }

        $this->diyPageRepository->update((int) $row['id'], [
            'components_draft' => $clean,
            'page_settings'    => $pageSettings,
        ]);
    }

    public function publish(string $key): void
    {
        $this->runInTransaction(function () use ($key): void {
            $row = $this->diyPageRepository->findByKey($key);
            if ($row === null) {
                if (!isset(self::SYSTEM_PAGES[$key])) {
                    throw new NotFoundException();
                }
                throw new BusinessException(lang('diy.draft_required'), 422);
            }

            $draft = $this->normalizeComponents($row['components_draft'] ?? []);
            $settings = $this->normalizeSettings($row['page_settings'] ?? []);
            if ($draft !== ($row['components_draft'] ?? null) || $settings !== ($row['page_settings'] ?? null)) {
                $this->diyPageRepository->update((int) $row['id'], [
                    'components_draft' => $draft,
                    'page_settings'    => $settings,
                ]);
            }

            $this->diyPageRepository->publishByKey($key, $draft, $settings);
            $this->diyPageVersionRepository->insertSnapshot(
                (int) $row['id'],
                $draft,
                $settings,
                RequestContext::actingUser()
            );
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPageVersions(string $key): array
    {
        $row = $this->diyPageRepository->findByKey($key);
        if ($row === null) {
            if (!isset(self::SYSTEM_PAGES[$key])) {
                throw new NotFoundException();
            }

            return [];
        }

        return $this->diyPageVersionRepository->listByPageId((int) $row['id']);
    }

    public function restorePageVersion(string $key, int $versionId): void
    {
        $this->runInTransaction(function () use ($key, $versionId): void {
            $page = $this->diyPageRepository->findByKey($key);
            if ($page === null) {
                throw new NotFoundException();
            }
            $ver = $this->diyPageVersionRepository->find($versionId);
            if ($ver === null || (int) $ver['page_id'] !== (int) $page['id']) {
                throw new NotFoundException();
            }
            $this->diyPageRepository->update((int) $page['id'], [
                'components_draft' => $this->normalizeComponents($ver['components'] ?? []),
                'page_settings'    => $this->normalizeSettings($ver['page_settings'] ?? []),
            ]);
        });
    }

    /**
     * C 端已发布页。未发布 / 禁用 / 已发布树为空 → null。丢掉不在 TYPES 里的 type。
     *
     * @return array{title: string, components: list<array<string, mixed>>, page_settings: array<string, mixed>}|null
     */
    public function getPublished(string $key, string $platform = 'uniapp'): ?array
    {
        $row = $this->diyPageRepository->findByKey($key, $platform);
        if ($row === null || (int) ($row['status'] ?? 0) !== 1) {
            return null;
        }
        $components = $this->filterBuiltinComponents($this->normalizeComponents($row['components_published'] ?? []));
        if ($components === []) {
            return null;
        }

        return [
            'title'         => (string) ($row['title'] ?? ''),
            'components'    => $components,
            'page_settings' => $this->normalizeSettings($row['page_settings'] ?? []),
        ];
    }

    /**
     * @return array{builtins: list<string>, plugins: list<mixed>, member_stats: list<array{key: string, label: string}>}
     */
    public function widgets(): array
    {
        return [
            'builtins'     => DiyWidgetRegistry::TYPES,
            'plugins'      => [],
            'member_stats' => [
                ['key' => 'user.balance', 'label' => lang('diy.stat_balance')],
                ['key' => 'user.points', 'label' => lang('diy.stat_points')],
            ],
        ];
    }

    /**
     * @return array{components: list<array<string, mixed>>, page_settings: array<string, mixed>}
     */
    public function getHomeDraft(): array
    {
        return $this->getDraft('home');
    }

    /**
     * @param array<int, mixed> $components
     * @param array<string, mixed> $pageSettings
     */
    public function saveHomeDraft(array $components, array $pageSettings): void
    {
        $this->saveDraft('home', $components, $pageSettings);
    }

    public function publishHome(): void
    {
        $this->publish('home');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listHomeVersions(): array
    {
        return $this->listPageVersions('home');
    }

    public function restoreHomeVersion(int $versionId): void
    {
        $this->restorePageVersion('home', $versionId);
    }

    /**
     * @return array{title: string, published: bool, component_count: int, updated_at: mixed}
     */
    public function getHomeSummary(): array
    {
        return $this->getPageSummary('home');
    }

    /**
     * @return array{title: string, published: bool, component_count: int, updated_at: mixed}
     */
    public function getPageSummary(string $key): array
    {
        if (!isset(self::SYSTEM_PAGES[$key])) {
            throw new BusinessException(lang('diy.system_page_only'), 422);
        }
        $row = $this->diyPageRepository->findByKey($key);
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
        $count = count($draft) > 0 ? count($draft) : count($published);

        return [
            'title'           => (string) ($row['title'] ?? self::SYSTEM_PAGES[$key]['title']),
            'published'       => count($published) > 0,
            'component_count' => $count,
            'updated_at'      => $row['updated_at'] ?? null,
        ];
    }

    /**
     * @param array<int, mixed> $components
     * @return list<array<string, mixed>>
     */
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
     * @return list<array<string, mixed>>
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
        if ($raw !== [] && !$this->isListArray($raw)) {
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

    /** @return array<string, mixed> */
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
}
