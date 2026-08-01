<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\service\mobile;

use app\repository\mobile\MobileConfigRepository;
use app\service\diy\DiyPageService;
use core\base\Service;
use core\exception\BusinessException;

class MobileConfigService extends Service
{
    protected MobileConfigRepository $repository;
    protected DiyPageService $diyPageService;

    public const BUILTIN_PAGES = [
        '__home__'     => 'pages/index/index',
        '__discover__' => 'pages/discover/index',
        '__message__'  => 'pages/message/index',
        '__my__'       => 'pages/my/index',
    ];

    /** @return array{homeOptions:array,tabBarOptions:array} */
    public function listEligible(): array
    {
        $opts = [];
        $labels = [
            '__home__'     => '首页',
            '__discover__' => '发现',
            '__message__'  => '消息',
            '__my__'       => '我的',
        ];
        foreach (self::BUILTIN_PAGES as $code => $path) {
            $opts[] = [
                'code'              => $code,
                'name'              => $labels[$code] ?? $code,
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

    /** @return array<string,mixed> */
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

    /** @return array<int,array<string,string>> */
    private function defaultTabbar(): array
    {
        return [
            ['code' => '__home__', 'path' => 'pages/index/index', 'text' => '首页', 'icon' => '/static/diy/tabbar/home.png', 'selected_icon' => '/static/diy/tabbar/home-active.png'],
            ['code' => '__discover__', 'path' => 'pages/discover/index', 'text' => '发现', 'icon' => '/static/diy/tabbar/discover.png', 'selected_icon' => '/static/diy/tabbar/discover-active.png'],
            ['code' => '__message__', 'path' => 'pages/message/index', 'text' => '消息', 'icon' => '/static/diy/tabbar/message.png', 'selected_icon' => '/static/diy/tabbar/message-active.png'],
            ['code' => '__my__', 'path' => 'pages/my/index', 'text' => '我的', 'icon' => '/static/diy/tabbar/my.png', 'selected_icon' => '/static/diy/tabbar/my-active.png'],
        ];
    }

    /** @return array<string,mixed> */
    public function get(): array
    {
        $row = $this->repository->findSingleton();
        if (!$row) {
            $config = $this->defaults();
            $config['home_decoration'] = $this->diyPageService->getPublishedHome();
            return $config;
        }
        $themeColors = $this->decodeJson($row['theme_colors'] ?? null);
        if (empty($themeColors['primary']) && ($row['theme_color'] ?? '') !== '') {
            $themeColors['primary'] = (string) $row['theme_color'];
        }
        $config = [
            'app_name'      => (string) ($row['app_name'] ?? ''),
            'app_logo'      => (string) ($row['app_logo'] ?? ''),
            'theme_color'   => (string) ($row['theme_color'] ?? ''),
            'theme_colors'  => $themeColors,
            'home_app_code' => (string) ($row['home_app_code'] ?? ''),
            'home_page'     => (string) ($row['home_page'] ?? ''),
            'tabbar'        => is_array($row['tabbar_json'] ?? null) ? $row['tabbar_json'] : [],
            'tabbar_style'  => $this->decodeJson($row['tabbar_style'] ?? null),
            'status'        => (int) ($row['status'] ?? 1),
        ];
        $config['home_decoration'] = $this->diyPageService->getPublishedHome();
        return $config;
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function save(array $input): array
    {
        $patch = [];
        foreach (['app_name', 'app_logo', 'theme_color', 'home_app_code', 'home_page'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = (string) $input[$f];
            }
        }
        if (array_key_exists('theme_colors', $input)) {
            $colors = is_array($input['theme_colors']) ? $input['theme_colors'] : [];
            $this->assertColors($colors, 'theme_colors');
            $patch['theme_colors'] = json_encode((object) $colors, JSON_UNESCAPED_UNICODE);
            if (isset($colors['primary'])) {
                $patch['theme_color'] = (string) $colors['primary'];
            }
        }
        if (array_key_exists('tabbar_style', $input)) {
            $ts = is_array($input['tabbar_style']) ? $input['tabbar_style'] : [];
            $this->assertColors($ts, 'tabbar_style');
            $patch['tabbar_style'] = json_encode((object) $ts, JSON_UNESCAPED_UNICODE);
        }
        if (array_key_exists('tabbar', $input)) {
            $patch['tabbar_json'] = json_encode($this->validateTabbar((array) $input['tabbar']), JSON_UNESCAPED_UNICODE);
        }
        if ($patch === []) {
            return $this->get();
        }
        $this->repository->upsert($patch);
        return $this->get();
    }

    /** @param array<int,mixed> $items @return array<int,array<string,mixed>> */
    private function validateTabbar(array $items): array
    {
        if (count($items) > 5) {
            throw new BusinessException('底部导航最多 5 项', 422);
        }
        $out = [];
        foreach ($items as $i => $item) {
            if (!is_array($item)) {
                throw new BusinessException("tabbar[{$i}] 格式错误", 422);
            }
            $path = ltrim(trim((string) ($item['path'] ?? '')), '/');
            $text = trim((string) ($item['text'] ?? ''));
            $code = trim((string) ($item['code'] ?? ''));
            if ($path === '' || $text === '') {
                throw new BusinessException("tabbar[{$i}] 缺少 path/text", 422);
            }
            // 自定义跳转链接：不再强制 path 与内置 code 一一对应；code 空时按 path 生成
            if ($code === '') {
                $builtinCode = array_search($path, self::BUILTIN_PAGES, true);
                $code = $builtinCode !== false
                    ? (string) $builtinCode
                    : '__custom_' . substr(md5($path), 0, 8) . '__';
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

    /** @param array<string,mixed> $colors */
    private function assertColors(array $colors, string $field): void
    {
        foreach ($colors as $k => $v) {
            if (!is_string($v) || ($v !== '' && !preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $v))) {
                throw new BusinessException("{$field}.{$k} 颜色格式非法", 422);
            }
        }
    }

    /** @return array<string,mixed> */
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
