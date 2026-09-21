<?php

declare(strict_types=1);

namespace app\service\diy;

use app\repository\article\ArticleRepository;
use app\repository\diy\DiyPageRepository;
use app\repository\diy\DiyPageVersionRepository;
use core\base\Service;
use core\context\RequestContext;
use core\diy\DiyWidgetRegistry;
use core\exception\BusinessException;
use core\exception\NotFoundException;
use core\exception\ValidationException;
use DI\Attribute\Inject;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * 装修页面：系统页草稿/发布/版本、自定义页 CRUD / 复制 / 删除保护、C 端已发布。
 *
 * 只调 Repository。链接目录见 LinkCatalogService；content-list 注水走 ArticleRepository。
 */
class DiyPageService extends Service
{
    private const SLUG_RE = '/^[a-z0-9][a-z0-9-]{0,62}[a-z0-9]$/';

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

    #[Inject]
    protected ArticleRepository $articleRepository;

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

        foreach ($components as $i => $component) {
            $type = (string) ($component['type'] ?? '');
            if ($type !== 'content-list') {
                continue;
            }
            $props = is_array($component['props'] ?? null) ? $component['props'] : [];
            $components[$i]['props'] = $this->hydrateProps($type, $props);
        }

        return [
            'title'         => (string) ($row['title'] ?? ''),
            'components'    => $components,
            'page_settings' => $this->normalizeSettings($row['page_settings'] ?? []),
        ];
    }

    /**
     * 管理端组件预览：未知 type → 422；content-list + source=latest 注入 items。
     *
     * @param array<string, mixed> $props
     * @return array{props: array<string, mixed>}
     */
    public function previewWidget(string $type, array $props): array
    {
        if (!in_array($type, DiyWidgetRegistry::TYPES, true)) {
            throw new ValidationException(['type' => lang('diy.widget_type_invalid')]);
        }

        return ['props' => $this->hydrateProps($type, $props)];
    }

    /**
     * @param array<string, mixed> $props
     * @return array<string, mixed>
     */
    private function hydrateProps(string $type, array $props): array
    {
        if ($type !== 'content-list' || (string) ($props['source'] ?? 'latest') !== 'latest') {
            return $props;
        }
        $limit = min(20, max(1, (int) ($props['limit'] ?? 6)));
        $params = [];
        if (isset($props['category_id']) && (int) $props['category_id'] > 0) {
            $params['category_id'] = (int) $props['category_id'];
        }
        $result = $this->articleRepository->getPublishedList($params, 1, $limit);
        $props['items'] = array_map(static function (array $row): array {
            return [
                'id'    => (int) ($row['id'] ?? 0),
                'title' => (string) ($row['title'] ?? ''),
                'cover' => (string) ($row['cover'] ?? ''),
                'date'  => (string) ($row['publish_at'] ?? $row['created_at'] ?? ''),
            ];
        }, $result['list']);

        return $props;
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
     * 自定义页分页。形状是 {list,total}，不是标准 {list,pagination}。
     *
     * @return array{list: list<array<string, mixed>>, total: int}
     */
    public function listPages(int $page = 1, int $limit = 10, string $keyword = '', ?bool $published = null): array
    {
        return $this->diyPageRepository->listPages($page, $limit, trim($keyword), $published);
    }

    /**
     * 新建自定义页。slug 在 Service 再断言一次（含 home/member 保留字）。
     *
     * @param array<string, mixed> $data 控制器 validate() 白名单
     * @return array{id: int}
     */
    public function createPage(array $data): array
    {
        $key = (string) $data['page_key'];
        $this->assertSlug($key);
        if ($this->diyPageRepository->existsKey($key)) {
            throw self::pageKeyTaken();
        }

        try {
            $row = $this->diyPageRepository->create([
                'page_type'             => 'custom',
                'page_key'              => $key,
                'platform'              => 'uniapp',
                'title'                 => (string) $data['title'],
                'components_draft'      => [],
                'components_published'  => [],
                'page_settings'         => [],
                'status'                => 1,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw self::pageKeyTaken();
        }

        return ['id' => (int) $row['id']];
    }

    /**
     * 改自定义页 title / page_key / status。非 custom → 404。
     *
     * @param array<string, mixed> $data 控制器 validate() 白名单
     */
    public function updatePage(int $id, array $data): void
    {
        $row = $this->guardCustom($id);
        $patch = [];
        if (array_key_exists('title', $data)) {
            $patch['title'] = (string) $data['title'];
        }
        if (array_key_exists('status', $data)) {
            $patch['status'] = (int) $data['status'] === 1 ? 1 : 0;
        }
        if (array_key_exists('page_key', $data)) {
            $key = (string) $data['page_key'];
            $this->assertSlug($key);
            if ($key !== (string) $row['page_key'] && $this->diyPageRepository->existsKey($key)) {
                throw self::pageKeyTaken();
            }
            $patch['page_key'] = $key;
        }
        if ($patch === []) {
            return;
        }

        try {
            $this->diyPageRepository->update($id, $patch);
        } catch (UniqueConstraintViolationException) {
            throw self::pageKeyTaken();
        }
    }

    /**
     * 复制自定义页。草稿空则抄已发布；副本恒未发布。
     *
     * @return array{id: int}
     */
    public function copyPage(int $id): array
    {
        return $this->runInTransaction(function () use ($id): array {
            $src = $this->guardCustom($id);
            $draft = $this->normalizeComponents($src['components_draft'] ?? []);
            if ($draft === []) {
                $draft = $this->normalizeComponents($src['components_published'] ?? []);
            }

            try {
                $row = $this->diyPageRepository->create([
                    'page_type'            => 'custom',
                    'page_key'             => $this->nextCopyKey((string) $src['page_key']),
                    'platform'             => 'uniapp',
                    'title'                => mb_substr((string) $src['title'], 0, 90) . '-副本',
                    'components_draft'     => $draft,
                    'components_published' => [],
                    'page_settings'        => $this->normalizeSettings($src['page_settings'] ?? []),
                    'status'               => (int) ($src['status'] ?? 1),
                ]);
            } catch (UniqueConstraintViolationException) {
                throw self::pageKeyTaken();
            }

            return ['id' => (int) $row['id']];
        });
    }

    public function deletePage(int $id): void
    {
        $row = $this->diyPageRepository->find($id);
        if ($row === null) {
            throw new NotFoundException();
        }
        $key = (string) ($row['page_key'] ?? '');
        $type = (string) ($row['page_type'] ?? '');
        if (isset(self::SYSTEM_PAGES[$key]) || $type === 'home' || $type === 'member') {
            throw new BusinessException(lang('diy.system_page_protected'), 400);
        }
        $this->guardCustom($id);
        $this->diyPageRepository->softDelete($id);
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

    /** slug：小写字母数字连字符，2–64 位；home/member 保留。 */
    private function assertSlug(string $key): void
    {
        if (isset(self::SYSTEM_PAGES[$key]) || preg_match(self::SLUG_RE, $key) !== 1) {
            throw new ValidationException(['page_key' => lang('diy.page_key_invalid')]);
        }
    }

    /**
     * 非 custom（含系统页、不存在）→ 404。
     *
     * @return array<string, mixed>
     */
    private function guardCustom(int $id): array
    {
        $row = $this->diyPageRepository->find($id);
        if ($row === null || ($row['page_type'] ?? '') !== 'custom') {
            throw new NotFoundException();
        }

        return $row;
    }

    /** 生成不冲突的副本标识：{src}-copy、{src}-copy2…；截断源 key 保证总长 ≤64。 */
    private function nextCopyKey(string $sourceKey): string
    {
        $base = rtrim(mb_substr($sourceKey, 0, 64 - 7), '-');
        for ($i = 1; $i <= 99; $i++) {
            $key = $base . '-copy' . ($i === 1 ? '' : (string) $i);
            if (!$this->diyPageRepository->existsKey($key)) {
                return $key;
            }
        }
        throw new ValidationException(['page_key' => lang('diy.copy_exhausted')]);
    }

    private static function pageKeyTaken(): ValidationException
    {
        return new ValidationException(['page_key' => lang('diy.page_key_exists')]);
    }
}
