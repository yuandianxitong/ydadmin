<?php

declare(strict_types=1);

namespace core\module;

/** 只读 JSON 模块契约；不执行安装脚本，也不自动授予权限或加载插件。 */
final class ModuleDescriptor
{
    /** @param array<string, mixed> $data */
    public function __construct(public readonly array $data)
    {
        if (($data['schema_version'] ?? null) !== 1
            || !is_string($data['module'] ?? null) || preg_match('/^[a-z][a-z0-9_]{0,30}\z/', $data['module']) !== 1
            || !is_string($data['model'] ?? null) || preg_match('/^[A-Z][A-Za-z0-9]{0,40}\z/', $data['model']) !== 1
            || !is_string($data['id'] ?? null) || preg_match('/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*\z/', $data['id']) !== 1
            || !is_string($data['table'] ?? null) || !is_array($data['permissions'] ?? null)
            || !is_array($data['routes'] ?? null) || !is_array($data['artifacts'] ?? null)) {
            throw new \InvalidArgumentException('模块描述格式不合法');
        }
        if ($data['id'] !== $data['module'] . '.' . (new \core\module\NameConvention())->snake($data['model'])
            || !is_string($data['title'] ?? null) || !is_string($data['primary_key'] ?? null)
            || !is_array($data['capabilities'] ?? null) || !is_array($data['fields'] ?? null)) {
            throw new \InvalidArgumentException('模块标识或字段描述不合法');
        }
        $permissionPrefix = $data['permission_prefix'] ?? $data['id'];
        if (!is_string($permissionPrefix) || preg_match('/^[a-z][a-z0-9_.]*\z/', $permissionPrefix) !== 1) {
            throw new \InvalidArgumentException('模块权限命名空间不合法');
        }
        foreach ($data['permissions'] as $permission) {
            if (!is_string($permission) || !str_starts_with($permission, $permissionPrefix . '.') || preg_match('/^[a-z][a-z0-9_.-]*\z/', $permission) !== 1) {
                throw new \InvalidArgumentException('模块权限前缀不合法');
            }
        }
        foreach ($data['routes'] as $route) {
            if (!is_array($route) || !in_array($route['method'] ?? null, ['GET', 'POST', 'PUT', 'DELETE'], true)
                || !is_string($route['action'] ?? null) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*\z/', $route['action']) !== 1
                || !is_string($route['path'] ?? null) || ($route['path'] !== '/adminapi/' . $data['module'] && !str_starts_with($route['path'], '/adminapi/' . $data['module'] . '/'))
                || !in_array($route['permission'] ?? null, $data['permissions'], true)) {
                throw new \InvalidArgumentException('模块路由或权限不合法');
            }
        }
        foreach ($data['fields'] as $field) {
            if (!is_array($field) || !is_string($field['name'] ?? null) || !is_string($field['type'] ?? null)) {
                throw new \InvalidArgumentException('模块字段描述不合法');
            }
            foreach (['searchable', 'in_list', 'in_form'] as $flag) {
                if (array_key_exists($flag, $field) && !is_bool($field[$flag])) {
                    throw new \InvalidArgumentException('模块字段开关必须是布尔值');
                }
            }
            if (isset($field['form_type']) && !in_array($field['form_type'], ['input', 'textarea', 'number', 'switch', 'select', 'datepicker', 'image'], true)) {
                throw new \InvalidArgumentException('模块表单类型不合法');
            }
        }
        foreach ($data['artifacts'] as $path) {
            if (!is_string($path) || preg_match('~^(server/(app|resource/lang|database/generated|modules)/|admin/src/(api|views)/)[A-Za-z0-9_./-]+\z~', $path) !== 1
                || str_contains($path, '..') || str_contains($path, '//')) {
                throw new \InvalidArgumentException('模块产物路径不合法');
            }
        }
    }

    public function toJson(): string
    {
        return json_encode($this->data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    }
}
