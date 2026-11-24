<?php

declare(strict_types=1);

namespace app\service\plugin\adapter;

use core\plugin\contracts\PluginHostProfile;

final class HostProfile implements PluginHostProfile
{
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
                'modules' => ['http'],
                'sdkSource' => 'admin/build/host-sdk.ts',
            ],
            'pc' => [
                'vendors' => ['vue', 'pinia', 'naive-ui'],
                'prefix' => '@yd/pc-host',
                'modules' => ['http'],
                'sdkSource' => 'pc/build/host-sdk.ts',
            ],
        ];
    }

    public function sourceEnds(): array
    {
        return ['admin', 'uniapp', 'pc'];
    }
}
