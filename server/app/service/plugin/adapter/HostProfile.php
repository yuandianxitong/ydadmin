<?php

declare(strict_types=1);

namespace app\service\plugin\adapter;

use core\plugin\contracts\IdentifiedPluginHostProfile;

final class HostProfile implements IdentifiedPluginHostProfile
{
    public function hostId(): string
    {
        return 'framework-webman';
    }

    public function strictMigrationHash(): bool
    {
        return (bool) config('plugin_host.migration_strict_hash', false);
    }

    public function categories(): array
    {
        return (array) config('plugin_host.categories');
    }

    public function reloadTimeoutSeconds(): int
    {
        return (int) config('plugin_host.reload_verify_timeout', 60);
    }

    public function reloadPollIntervalMs(): int
    {
        return (int) config('plugin_host.reload_poll_interval_ms', 200);
    }

    public function publishableFrontends(): array
    {
        return [
            'admin' => [
                'vendors' => [
                    'vue', 'vue-router', 'pinia', 'vue-i18n', '@vueuse/core',
                    'element-plus', '@element-plus/icons-vue', 'axios',
                ],
                'prefix' => '@yd/admin-host',
                // 与 admin/build/host-sdk.ts 的 HOST_MODULES、build-plugin-admin.mjs 的 EXTERNALS 同一份
                'modules' => ['http', 'i18n', 'router', 'store'],
                'sdkSource' => 'admin/build/host-sdk.ts',
            ],
        ];
    }

    public function sourceEnds(): array
    {
        return ['admin'];
    }
}
