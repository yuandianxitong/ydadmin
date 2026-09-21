<?php

declare(strict_types=1);

namespace app\service\mobile;

use app\repository\mobile\MobileConfigRepository;
use app\service\diy\DiyPageService;
use core\base\Service;
use core\exception\ValidationException;
use DI\Attribute\Inject;

/**
 * 移动端主题与底部导航配置。
 *
 * 只调 Repository。JSON 列由模型 array cast 编码，禁止 json_encode 后再写入。
 * get() 无行时返回 defaults()，不 insert；save 才 upsert。
 */
class MobileConfigService extends Service
{
    public const BUILTIN_PAGES = [
        '__home__'     => 'pages/index/index',
        '__discover__' => 'pages/discover/index',
        '__message__'  => 'pages/message/index',
        '__my__'       => 'pages/my/index',
    ];

    private const HEX_COLOR = '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/';

    /** @var list<string> */
    private const SAVE_FIELDS = [
        'app_name', 'app_logo', 'theme_color', 'theme_colors',
        'home_app_code', 'home_page', 'tabbar', 'tabbar_style', 'status',
    ];

    #[Inject]
    protected MobileConfigRepository $repository;

    #[Inject]
    protected DiyPageService $diyPageService;

    /**
     * @return array{homeOptions: array<int, mixed>, tabBarOptions: list<array<string, mixed>>}
     */
    public function listEligible(): array
    {
        $labels = [
            '__home__'     => lang('mobile.tab_home'),
            '__discover__' => lang('mobile.tab_discover'),
            '__message__'  => lang('mobile.tab_message'),
            '__my__'       => lang('mobile.tab_my'),
        ];
        $opts = [];
        foreach (self::BUILTIN_PAGES as $code => $path) {
            $opts[] = [
                'code'              => $code,
                'name'              => $labels[$code],
                'kind'              => 'builtin',
                'subpackage'        => '',
                'pages'             => [['path' => $path]],
                'default_home_path' => $path,
            ];
        }

        return [
            'homeOptions'   => [],
            'tabBarOptions' => $opts,
        ];
    }

    /** @return array<string, mixed> */
    public function defaults(): array
    {
        return [
            'app_name'      => '',
            'app_logo'      => '',
            'theme_color'   => '#2979ff',
            'theme_colors'  => [
                'primary'     => '#2979ff',
                'dark'        => '#1e5bb8',
                'price'       => '#fa3534',
                'page_bg'     => '#f5f5f5',
                'button_text' => '#ffffff',
                'badge'       => '#fa3534',
            ],
            'home_app_code' => '',
            'home_page'     => '',
            'tabbar'        => $this->defaultTabbar(),
            'tabbar_style'  => [
                'text_color'   => '#999999',
                'active_color' => '#2979ff',
                'bg_color'     => '#ffffff',
            ],
            'status' => 1,
        ];
    }

    /** @return array<string, mixed> */
    public function get(): array
    {
        $row = $this->repository->findSingleton();
        if ($row === null) {
            return $this->defaults();
        }
        $themeColors = $this->decodeJson($row['theme_colors'] ?? null);
        if (empty($themeColors['primary']) && ($row['theme_color'] ?? '') !== '') {
            $themeColors['primary'] = (string) $row['theme_color'];
        }

        return [
            'app_name'      => (string) ($row['app_name'] ?? ''),
            'app_logo'      => (string) ($row['app_logo'] ?? ''),
            'theme_color'   => (string) ($row['theme_color'] ?? ''),
            'theme_colors'  => $themeColors,
            'home_app_code' => (string) ($row['home_app_code'] ?? ''),
            'home_page'     => (string) ($row['home_page'] ?? ''),
            'tabbar'        => $this->decodeJson($row['tabbar_json'] ?? null),
            'tabbar_style'  => $this->decodeJson($row['tabbar_style'] ?? null),
            'status'        => (int) ($row['status'] ?? 1),
        ];
    }

    /** @return array<string, mixed> */
    public function getPublic(): array
    {
        $config = $this->get();
        $config['home_decoration'] = $this->diyPageService->getPublishedHome();

        return $config;
    }

    /**
     * @param array<string, mixed> $input 控制器 validate() 白名单
     * @return array<string, mixed>
     */
    public function save(array $input): array
    {
        $input = array_intersect_key($input, array_flip(self::SAVE_FIELDS));
        $patch = [];
        foreach (['app_name', 'app_logo', 'theme_color', 'home_app_code', 'home_page'] as $field) {
            if (!array_key_exists($field, $input)) {
                continue;
            }
            $value = (string) $input[$field];
            if ($field === 'theme_color') {
                $this->assertColorValue($value, 'theme_color');
            }
            $patch[$field] = $value;
        }
        if (array_key_exists('theme_colors', $input)) {
            $colors = is_array($input['theme_colors']) ? $input['theme_colors'] : [];
            $this->assertColors($colors, 'theme_colors');
            $patch['theme_colors'] = $colors;
            if (isset($colors['primary'])) {
                $patch['theme_color'] = (string) $colors['primary'];
            }
        }
        if (array_key_exists('tabbar_style', $input)) {
            $style = is_array($input['tabbar_style']) ? $input['tabbar_style'] : [];
            $this->assertColors($style, 'tabbar_style');
            $patch['tabbar_style'] = $style;
        }
        if (array_key_exists('tabbar', $input)) {
            $patch['tabbar_json'] = $this->validateTabbar(is_array($input['tabbar']) ? $input['tabbar'] : []);
        }
        if (array_key_exists('status', $input)) {
            $patch['status'] = (int) $input['status'] === 1 ? 1 : 0;
        }
        if ($patch === []) {
            return $this->get();
        }
        $this->runInTransaction(function () use ($patch): void {
            $this->repository->upsert($patch);
        });

        return $this->get();
    }

    /** @return list<array<string, string>> */
    private function defaultTabbar(): array
    {
        return [
            ['code' => '__home__', 'path' => 'pages/index/index', 'text' => '首页', 'icon' => '/static/diy/tabbar/home.png', 'selected_icon' => '/static/diy/tabbar/home-active.png'],
            ['code' => '__discover__', 'path' => 'pages/discover/index', 'text' => '发现', 'icon' => '/static/diy/tabbar/discover.png', 'selected_icon' => '/static/diy/tabbar/discover-active.png'],
            ['code' => '__message__', 'path' => 'pages/message/index', 'text' => '消息', 'icon' => '/static/diy/tabbar/message.png', 'selected_icon' => '/static/diy/tabbar/message-active.png'],
            ['code' => '__my__', 'path' => 'pages/my/index', 'text' => '我的', 'icon' => '/static/diy/tabbar/my.png', 'selected_icon' => '/static/diy/tabbar/my-active.png'],
        ];
    }

    /**
     * @param array<int, mixed> $items
     * @return list<array<string, string>>
     */
    private function validateTabbar(array $items): array
    {
        if (count($items) > 5) {
            throw new ValidationException(['tabbar' => lang('mobile.tabbar_too_many')]);
        }
        $out = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new ValidationException(['tabbar' => lang('mobile.tabbar_item_invalid')]);
            }
            $code = trim((string) ($item['code'] ?? ''));
            $path = ltrim(trim((string) ($item['path'] ?? '')), '/');
            $text = trim((string) ($item['text'] ?? ''));
            if ($code === '' || $path === '' || $text === '') {
                throw new ValidationException(['tabbar' => lang('mobile.tabbar_item_invalid')]);
            }
            $out[] = [
                'code'          => $code,
                'path'          => $path,
                'text'          => $text,
                'icon'          => (string) ($item['icon'] ?? ''),
                'selected_icon' => (string) ($item['selected_icon'] ?? ''),
                'sel_label'     => (string) ($item['sel_label'] ?? ''),
                'badge'         => (string) ($item['badge'] ?? ''),
            ];
        }

        return $out;
    }

    /** @param array<string|int, mixed> $colors */
    private function assertColors(array $colors, string $field): void
    {
        foreach ($colors as $value) {
            if (!is_string($value)) {
                throw new ValidationException([$field => lang('mobile.color_invalid')]);
            }
            $this->assertColorValue($value, $field);
        }
    }

    private function assertColorValue(string $value, string $field): void
    {
        if ($value !== '' && preg_match(self::HEX_COLOR, $value) !== 1) {
            throw new ValidationException([$field => lang('mobile.color_invalid')]);
        }
    }

    /** @return array<string, mixed> */
    private function decodeJson(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}
