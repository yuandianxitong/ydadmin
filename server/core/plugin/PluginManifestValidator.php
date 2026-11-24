<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */
declare(strict_types=1);

namespace core\plugin;

use core\plugin\contracts\ManifestExtension;
use core\plugin\contracts\PluginHostProfile;
use core\plugin\contracts\RouteBuckets;
use support\Container;

class PluginManifestValidator
{
    private const CODE_REGEX = PluginCodePattern::MANIFEST;
    private const VERSION_REGEX = '/^\d+\.\d+\.\d+$/';

    private readonly ?ManifestExtension $extension;

    private readonly RouteBuckets $buckets;

    public function __construct(
        private readonly PluginHostProfile $profile,
        ?ManifestExtension $extension = null,
        ?RouteBuckets $buckets = null,
    ) {
        $this->extension = $extension
            ?? (Container::has(ManifestExtension::class) ? Container::get(ManifestExtension::class) : null);
        $this->buckets = $buckets ?? Container::get(RouteBuckets::class);
    }

    /**
     * @param array<string, mixed> $manifest
     * @return string[] 空数组 = 通过；否则每条错误一项中文消息。
     */
    public function validate(array $manifest): array
    {
        $errors = [];

        // 1. 顶层必填
        foreach (['code', 'name', 'version', 'kind', 'psr4', 'lifecycle'] as $k) {
            if (empty($manifest[$k])) {
                $errors[] = "{$k} 不能为空";
            }
        }

        $code = (string) ($manifest['code'] ?? '');
        if ($code !== '' && !preg_match(self::CODE_REGEX, $code)) {
            $errors[] = "code 必须 kebab-case：{$code}";
        }
        if (!empty($manifest['version']) && !preg_match(self::VERSION_REGEX, (string) $manifest['version'])) {
            $errors[] = 'version 必须符合 semver';
        }
        $kind = (string) ($manifest['kind'] ?? '');
        if (!in_array($kind, ['app', 'plugin'], true)) {
            $errors[] = "kind 必须为 'app' 或 'plugin'";
        }

        // 2. psr4 命名空间检查
        foreach (array_keys((array) ($manifest['psr4'] ?? [])) as $ns) {
            if (!str_starts_with((string) $ns, 'Plugin\\')) {
                $errors[] = "psr4 命名空间必须以 Plugin\\ 开头：{$ns}";
            }
        }

        // 3. routes：宿主声明的路由分组里至少有一个非空。值是控制器目录，目录是否存在由 PluginLoader::load() 检查。
        $routes = (array) ($manifest['routes'] ?? []);
        $hasAnyRoute = false;
        foreach (array_keys($this->buckets->controllerDirs()) as $key) {
            if (!empty($routes[$key])) {
                $hasAnyRoute = true;
                break;
            }
        }
        if (!$hasAnyRoute) {
            $errors[] = $this->buckets->missingRoutesError();
        }

        // 5.5. icon：可选；为空或 http(s):// URL 直接放行；
        // 其它必须是单段文件名（kebab/digit/._-）+ 图片扩展名 —— 防 path traversal
        $icon = (string) ($manifest['icon'] ?? '');
        if ($icon !== '' && !preg_match('#^https?://#i', $icon)
            && !preg_match(PluginCodePattern::ICON_FILE, $icon)) {
            $errors[] = "icon 必须是单段图片文件名（如 icon.png）或 http(s):// URL：{$icon}";
        }

        // 6. depends
        $depends = (array) ($manifest['depends'] ?? []);
        foreach ($depends as $i => $d) {
            $d = (string) $d;
            if (!preg_match(self::CODE_REGEX, $d)) {
                $errors[] = "depends[{$i}] 必须 kebab-case：{$d}";
            }
            if ($d === $code) {
                $errors[] = "depends[{$i}] 不能引用自己：{$d}";
            }
        }

        // 6.1. core：可选的核心版本闭区间
        if (array_key_exists('core', $manifest)) {
            array_push($errors, ...self::coreErrors($manifest['core']));
        }

        // 6.5. diy_widgets（可选）：数组；每条须含非空 type；hydrator 若存在须 Plugin\ 命名空间。
        // 渲染器协议 v1：renderer（三端渲染器裸组件名 + protocol 版本闸）与 item_fields（item 字段追加白名单）
        if (array_key_exists('diy_widgets', $manifest)) {
            $dw = $manifest['diy_widgets'];
            if (!is_array($dw)) {
                $errors[] = 'diy_widgets 必须为数组';
            } else {
                foreach ($dw as $i => $w) {
                    if (!is_array($w) || empty($w['type'])) {
                        $errors[] = "diy_widgets[{$i}].type 不能为空";
                        continue;
                    }
                    if (isset($w['hydrator']) && !str_starts_with((string) $w['hydrator'], 'Plugin\\')) {
                        $errors[] = "diy_widgets[{$i}].hydrator 必须以 Plugin\\ 开头：{$w['hydrator']}";
                    }
                    if (array_key_exists('renderer', $w)) {
                        if (!is_array($w['renderer'])) {
                            $errors[] = "diy_widgets[{$i}].renderer 必须为对象";
                        } else {
                            $protocol = $w['renderer']['protocol'] ?? null;
                            if (!is_int($protocol)) {
                                $errors[] = "diy_widgets[{$i}].renderer.protocol 必填且必须为整数";
                            } elseif ($protocol > \core\diy\NormalizedWidget::SUPPORTED_PROTOCOL) {
                                $errors[] = "diy_widgets[{$i}].renderer.protocol {$protocol} 高于框架支持的最大版本 "
                                    . \core\diy\NormalizedWidget::SUPPORTED_PROTOCOL . '，请升级框架后重试';
                            }
                            foreach ($w['renderer'] as $end => $name) {
                                if ($end === 'protocol') {
                                    continue;
                                }
                                $ends = $this->profile->sourceEnds();
                                if (!in_array($end, $ends, true)) {
                                    $errors[] = "diy_widgets[{$i}].renderer.{$end} 不支持的端（仅 " . implode('/', $ends) . '）';
                                } elseif (!is_string($name) || !preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $name)) {
                                    // 裸组件名（约定目录 <端>/components/diy/ 下），禁止路径分隔符/扩展名，杜绝穿越
                                    $errors[] = "diy_widgets[{$i}].renderer.{$end} 必须是裸组件名（无斜杠/扩展名）";
                                }
                            }
                        }
                    }
                    // widget 图标：与顶层 icon 同规则（单段图片文件名或 http URL）；
                    // 文件放 src/tenant/assets/diy/，对外仍走 /plugin-icon/<code>/assets/diy/<file>
                    if (array_key_exists('icon', $w)) {
                        $wIcon = (string) $w['icon'];
                        if ($wIcon !== '' && !preg_match('#^https?://#i', $wIcon)
                            && !preg_match(PluginCodePattern::ICON_FILE, $wIcon)) {
                            $errors[] = "diy_widgets[{$i}].icon 必须是单段图片文件名（放 src/tenant/assets/diy/ 下）或 http(s):// URL：{$wIcon}";
                        }
                    }
                    if (array_key_exists('item_fields', $w)) {
                        if (!is_array($w['item_fields'])) {
                            $errors[] = "diy_widgets[{$i}].item_fields 必须为数组";
                        } elseif (count($w['item_fields']) > 8) {
                            $errors[] = "diy_widgets[{$i}].item_fields 最多 8 项";
                        } else {
                            foreach ($w['item_fields'] as $j => $f) {
                                $key = (string) (is_array($f) ? ($f['key'] ?? '') : '');
                                $ftype = (string) (is_array($f) ? ($f['type'] ?? 'text') : '');
                                if (!preg_match('/^[a-z][a-z0-9_]*$/', $key)) {
                                    $errors[] = "diy_widgets[{$i}].item_fields[{$j}].key 必须为 snake_case";
                                } elseif (in_array($key, ['title', 'image', 'desc', 'link', 'meta', 'icon', 'label', 'badge_key', 'value', 'received', 'progress'], true)) {
                                    $errors[] = "diy_widgets[{$i}].item_fields[{$j}].key '{$key}' 是基座字段，无需声明";
                                }
                                if (!in_array($ftype, ['text', 'raw'], true)) {
                                    $errors[] = "diy_widgets[{$i}].item_fields[{$j}].type 必须为 text 或 raw";
                                }
                            }
                        }
                    }
                }
            }
        }

        // 6.6. diy_links（装修链接声明，可选）：数组；每条须含非空 label 与 path
        if (array_key_exists('diy_links', $manifest)) {
            $dl = $manifest['diy_links'];
            if (!is_array($dl)) {
                $errors[] = 'diy_links 必须为数组';
            } else {
                foreach ($dl as $i => $l) {
                    if (!is_array($l)) {
                        $errors[] = "diy_links[{$i}] 必须为对象";
                        continue;
                    }
                    if (($l['label'] ?? '') === '') {
                        $errors[] = "diy_links[{$i}].label 不能为空";
                    }
                    if (($l['path'] ?? '') === '') {
                        $errors[] = "diy_links[{$i}].path 不能为空";
                    }
                    if (array_key_exists('params_schema', $l) && !is_array($l['params_schema'])) {
                        $errors[] = "diy_links[{$i}].params_schema 必须为数组";
                    }
                }
            }
        }

        // 6.7. console（可选）：CLI 命令类数组，每项必须为 Plugin\ 命名空间字符串，由 PluginConsoleRegistrar 注册。
        if (array_key_exists('console', $manifest)) {
            $console = $manifest['console'];
            if (!is_array($console)) {
                $errors[] = 'console 必须为数组';
            } else {
                foreach ($console as $i => $cls) {
                    if (!is_string($cls) || !str_starts_with($cls, 'Plugin\\')) {
                        $errors[] = "console[{$i}] 命令类必须位于 Plugin\\ 命名空间";
                    }
                }
            }
        }

        // 6.8. member_stats（会员统计键声明，可选）：对象 {provider, keys[]}；
        // provider 必须 Plugin\ 命名空间（与 hydrator 同规），key 限 [a-z0-9_]，keys ≤ 16。
        if (array_key_exists('member_stats', $manifest)) {
            $ms = $manifest['member_stats'];
            if (!is_array($ms)) {
                $errors[] = 'member_stats 必须为对象';
            } else {
                $provider = (string) ($ms['provider'] ?? '');
                if ($provider === '' || !str_starts_with($provider, 'Plugin\\')) {
                    $errors[] = 'member_stats.provider 必须为 Plugin\\ 命名空间类名';
                }
                $keys = $ms['keys'] ?? null;
                if (!is_array($keys) || $keys === []) {
                    $errors[] = 'member_stats.keys 必须为非空数组';
                } elseif (count($keys) > 16) {
                    $errors[] = 'member_stats.keys 数量不能超过 16';
                } else {
                    foreach ($keys as $i => $k) {
                        if (!is_array($k)) {
                            $errors[] = "member_stats.keys[{$i}] 必须为对象";
                            continue;
                        }
                        if (!preg_match('/^[a-z0-9_]+$/', (string) ($k['key'] ?? ''))) {
                            $errors[] = "member_stats.keys[{$i}].key 只允许小写字母/数字/下划线";
                        }
                        if (($k['label'] ?? '') === '') {
                            $errors[] = "member_stats.keys[{$i}].label 不能为空";
                        }
                    }
                }
            }
        }

        // 8. uniapp.*（取代旧 mobile.*）
        if (isset($manifest['uniapp'])) {
            $uniapp = (array) $manifest['uniapp'];
            // 严禁小写 tabbar
            if (array_key_exists('tabbar', $uniapp)) {
                $errors[] = 'uniapp.tabbar 拼写错误，必须是 uniapp.tabBar（驼峰）';
            }
            $pages = $uniapp['pages'] ?? null;
            $subpackage = $uniapp['subpackage'] ?? null;
            if ($pages && !$subpackage) {
                $errors[] = '声明 uniapp.pages 时必须同时声明 uniapp.subpackage';
            }
            if ($subpackage !== null && !is_string($subpackage)) {
                $errors[] = 'uniapp.subpackage 必须为字符串';
            }
            foreach ((array) ($pages ?? []) as $i => $page) {
                if (empty($page['path'])) {
                    $errors[] = "uniapp.pages[{$i}].path 不能为空";
                }
                if (empty($page['title'])) {
                    $errors[] = "uniapp.pages[{$i}].title 不能为空";
                }
            }
            // uniapp.tabBar 已废弃，与 allowTabBar 语义重叠，两者并存会出现矛盾配置。
            if (array_key_exists('tabBar', $uniapp)) {
                $errors[] = 'uniapp.tabBar 字段已废弃；如需声明插件可否作为租户 tabBar 入口，请使用 uniapp.allowTabBar（布尔）';
            }
            if (array_key_exists('allowHome', $uniapp) && !is_bool($uniapp['allowHome'])) {
                $errors[] = 'uniapp.allowHome 必须为布尔';
            }
            if (array_key_exists('allowTabBar', $uniapp) && !is_bool($uniapp['allowTabBar'])) {
                $errors[] = 'uniapp.allowTabBar 必须为布尔';
            }
        }

        // 8.5. events / subscriber：插件事件订阅（可选）
        if (array_key_exists('events', $manifest)) {
            $events = $manifest['events'];
            if (!is_array($events)) {
                $errors[] = 'events 必须为对象：{事件名: handler 类名 或 类名数组}';
            } else {
                foreach ($events as $event => $handlers) {
                    if (!is_string($event) || $event === '') {
                        $errors[] = 'events 的键（事件名）必须为非空字符串';
                        continue;
                    }
                    foreach ((array) $handlers as $h) {
                        if (!is_string($h) || !str_starts_with($h, 'Plugin\\')) {
                            $errors[] = "events['{$event}'] 的 handler 必须是 Plugin\\ 开头的类名";
                        }
                    }
                }
            }
        }
        if (array_key_exists('subscriber', $manifest)) {
            $sub = $manifest['subscriber'];
            if (!is_string($sub) || !str_starts_with($sub, 'Plugin\\')) {
                $errors[] = 'subscriber 必须是 Plugin\\ 开头的类名（实现 core\plugin\contracts\EventSubscriber）';
            }
        }

        // 9. pc.*：租户 PC 前台页面声明（可选）
        if (isset($manifest['pc'])) {
            $pc = (array) $manifest['pc'];
            if (array_key_exists('allowHome', $pc) && !is_bool($pc['allowHome'])) {
                $errors[] = 'pc.allowHome 必须为布尔';
            }
            if (array_key_exists('home', $pc) && !is_string($pc['home'])) {
                $errors[] = 'pc.home 必须为字符串';
            }
            $pages = $pc['pages'] ?? [];
            if (!is_array($pages)) {
                $errors[] = 'pc.pages 必须为数组';
            } else {
                foreach ($pages as $i => $page) {
                    if (!is_array($page)) {
                        $errors[] = "pc.pages[{$i}] 必须为对象";
                        continue;
                    }
                    $route = (string) ($page['route'] ?? '');
                    $component = (string) ($page['component'] ?? '');
                    if ($route === '' || !str_starts_with($route, '/')) {
                        $errors[] = "pc.pages[{$i}].route 必须以 / 开头";
                    }
                    if (($page['title'] ?? '') === '') {
                        $errors[] = "pc.pages[{$i}].title 不能为空";
                    }
                    if ($component === '') {
                        $errors[] = "pc.pages[{$i}].component 不能为空";
                    } elseif (!str_starts_with($component, 'plugins/' . $code . '/')) {
                        $errors[] = "pc.pages[{$i}].component 必须指向 plugins/{$code}/ 下的组件";
                    }
                    if (array_key_exists('nav', $page) && !is_bool($page['nav'])) {
                        $errors[] = "pc.pages[{$i}].nav 必须为布尔";
                    }
                    if (array_key_exists('auth', $page) && !is_bool($page['auth'])) {
                        $errors[] = "pc.pages[{$i}].auth 必须为布尔";
                    }
                }
            }
        }

        // 预留字段：目前没有任何运行时消费者，只挡类型错误和标识重复，不锁死协议细节，
        // 免得将来接线时同时改校验器与已发布的插件包。零消费者由 PluginReservedFieldsTest 守住。
        $errors = array_merge($errors, $this->validateReservedList($manifest, 'ai_tools', 'tool_code'));
        $errors = array_merge($errors, $this->validateReservedList($manifest, 'agent_templates', 'code'));
        $errors = array_merge($errors, $this->validateReservedList($manifest, 'workflow_nodes', 'code'));

        // ai_tools 额外要求 endpoint 非空：它是调用入口，缺了这条声明无法被解析成可调用的东西。
        foreach ((array) ($manifest['ai_tools'] ?? []) as $i => $tool) {
            if (is_array($tool) && empty($tool['endpoint'])) {
                $errors[] = "ai_tools[{$i}].endpoint 不能为空";
            }
        }

        if ($this->extension !== null) {
            $errors = array_merge($errors, $this->extension->validate($manifest));
        }

        return $errors;
    }

    /** @return string[] */
    private static function coreErrors(mixed $core): array
    {
        if (!is_array($core) || ($core !== [] && array_is_list($core))) {
            return ['core 必须为对象'];
        }

        $errors = [];
        foreach (array_diff(array_keys($core), ['min', 'max']) as $key) {
            $errors[] = "core 不支持的键：{$key}";
        }
        foreach (['min', 'max'] as $key) {
            if (array_key_exists($key, $core)
                && (!is_string($core[$key]) || !preg_match(self::VERSION_REGEX, $core[$key]))) {
                $errors[] = "core.{$key} 必须是 x.y.z 形式的版本号";
            }
        }
        if ($errors === [] && isset($core['min'], $core['max'])
            && version_compare($core['min'], $core['max'], '>')) {
            $errors[] = "core.min {$core['min']} 不能大于 core.max {$core['max']}";
        }

        return $errors;
    }

    /**
     * 预留字段的共用骨架校验：可选；存在则必须是数组；每项必须是数组、
     * 有非空 kebab-case 的标识键，且该标识在同一数组内唯一。
     *
     * @param array<string, mixed> $manifest
     * @return array<int, string>
     */
    private function validateReservedList(array $manifest, string $field, string $idKey): array
    {
        if (!array_key_exists($field, $manifest)) {
            return [];
        }
        $value = $manifest[$field];
        if (!is_array($value)) {
            return ["{$field} 必须是数组"];
        }

        $errors = [];
        $seen = [];
        foreach ($value as $i => $item) {
            if (!is_array($item)) {
                $errors[] = "{$field}[{$i}] 必须是数组";
                continue;
            }
            $id = (string) ($item[$idKey] ?? '');
            if ($id === '') {
                $errors[] = "{$field}[{$i}].{$idKey} 不能为空";
                continue;
            }
            if (!preg_match(self::CODE_REGEX, $id)) {
                $errors[] = "{$field}[{$i}].{$idKey} 必须 kebab-case：{$id}";
                continue;
            }
            if (isset($seen[$id])) {
                $errors[] = "{$field}[{$i}].{$idKey} 重复：{$id}";
                continue;
            }
            $seen[$id] = true;
        }
        return $errors;
    }

    /**
     * 填充默认值（宿主专有字段的默认值由 ManifestExtension::normalize() 填）：
     * - uniapp.allowHome  默认值：kind=app → true，kind=plugin → false
     * - uniapp.allowTabBar 默认值：kind=app → true，kind=plugin → false
     * - category 默认值：未声明或不在码表内统一归 'other'
     *
     * @param array<string, mixed> $manifest
     * @return array<string, mixed>
     */
    public function normalize(array $manifest): array
    {
        $manifest = $this->extension?->normalize($manifest) ?? $manifest;
        if (isset($manifest['uniapp']) && is_array($manifest['uniapp'])) {
            $isApp = ($manifest['kind'] ?? '') === 'app';
            if (!array_key_exists('allowHome', $manifest['uniapp'])) {
                $manifest['uniapp']['allowHome'] = $isApp;
            }
            if (!array_key_exists('allowTabBar', $manifest['uniapp'])) {
                $manifest['uniapp']['allowTabBar'] = $isApp;
            }
        }
        if (isset($manifest['pc']) && is_array($manifest['pc'])) {
            $isApp = ($manifest['kind'] ?? '') === 'app';
            if (!array_key_exists('allowHome', $manifest['pc'])) {
                $manifest['pc']['allowHome'] = $isApp;
            }
        }
        // 插件市场分类：未声明或不在码表内统一归 'other'
        $manifest['category'] = \core\plugin\PluginCategory::normalize($manifest['category'] ?? null, $this->profile->categories());
        return $manifest;
    }
}
