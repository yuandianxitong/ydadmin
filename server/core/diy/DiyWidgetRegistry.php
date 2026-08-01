<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace core\diy;

use core\exception\BusinessException;

/**
 * 装修 widget 白名单 + 结构校验（纯逻辑）。
 * 15 个通用 widget（含 member 个人中心专用的 user-info-card / service-menu，以及 content-list 文章列表）。
 */
class DiyWidgetRegistry
{
    public const TYPES = [
        'banner', 'nav-grid', 'category-nav', 'rich-text', 'title-bar', 'divider',
        'image-ad', 'image-cube', 'video', 'notice', 'search-bar', 'float-button',
        'user-info-card', 'service-menu', 'content-list',
    ];

    /**
     * @param array<int,mixed> $components
     * @param array<int,string>|null $allowedTypes
     * @return array<int,array{id:string,type:string,props:array,hidden?:bool}>
     */
    public function validate(array $components, ?array $allowedTypes = null): array
    {
        $allowed = $allowedTypes ?? self::TYPES;
        $clean = [];
        $seen = [];
        foreach ($components as $i => $c) {
            if (!is_array($c)) {
                throw new BusinessException("组件[{$i}]格式错误", 422);
            }
            $id = (string) ($c['id'] ?? '');
            $type = (string) ($c['type'] ?? '');
            if ($id === '') {
                throw new BusinessException("组件[{$i}]缺少 id", 422);
            }
            if (isset($seen[$id])) {
                throw new BusinessException("组件 id 重复：{$id}", 422);
            }
            $seen[$id] = true;
            if (!in_array($type, $allowed, true)) {
                throw new BusinessException("组件[{$i}]类型不支持：{$type}", 422);
            }
            $props = $c['props'] ?? [];
            if (!is_array($props)) {
                throw new BusinessException("组件[{$i}]props 必须为对象", 422);
            }
            $item = ['id' => $id, 'type' => $type, 'props' => $props];
            if (array_key_exists('hidden', $c)) {
                $item['hidden'] = (bool) $c['hidden'];
            }
            $clean[] = $item;
        }
        return $clean;
    }
}
