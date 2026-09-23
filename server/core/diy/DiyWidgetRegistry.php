<?php

declare(strict_types=1);

namespace core\diy;

use core\exception\ValidationException;

class DiyWidgetRegistry
{
    public const TYPES = [
        'banner', 'nav-grid', 'category-nav', 'rich-text', 'title-bar', 'divider',
        'image-ad', 'image-cube', 'video', 'notice', 'search-bar', 'float-button',
        'user-info-card', 'service-menu', 'content-list',
    ];

    /**
     * @param array<int, mixed> $components
     * @param list<string>|null $allowedTypes
     * @return array<int, array{id:string,type:string,props:array<string,mixed>,hidden?:bool}>
     */
    public function validate(array $components, ?array $allowedTypes = null): array
    {
        $allowed = $allowedTypes ?? self::TYPES;
        $clean = [];
        $seen = [];
        foreach ($components as $i => $c) {
            if (!is_array($c)) {
                throw new ValidationException(['components' => lang('diy.widget_invalid')]);
            }
            // 数组/对象值会触发 Array to string conversion，webman 把 warning 转成 ErrorException → 500
            if (!is_scalar($c['id'] ?? '') || !is_scalar($c['type'] ?? '')) {
                throw new ValidationException(['components' => lang('diy.widget_invalid')]);
            }
            $id = (string) ($c['id'] ?? '');
            $type = (string) ($c['type'] ?? '');
            if ($id === '') {
                throw new ValidationException(['components' => lang('diy.widget_id_required')]);
            }
            if (isset($seen[$id])) {
                throw new ValidationException(['components' => lang('diy.widget_id_duplicate')]);
            }
            $seen[$id] = true;
            if (!in_array($type, $allowed, true)) {
                throw new ValidationException(['components' => lang('diy.widget_type_invalid')]);
            }
            $props = $c['props'] ?? [];
            if (!is_array($props)) {
                throw new ValidationException(['components' => lang('diy.widget_props_invalid')]);
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
