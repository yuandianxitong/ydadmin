<?php

declare(strict_types=1);

namespace app\service\plugin\adapter;

use app\middleware\AdminAuthMiddleware;
use app\middleware\AdminLogMiddleware;
use app\middleware\AdminPermissionMiddleware;
use app\middleware\ApiAuthMiddleware;
use app\middleware\IdempotencyMiddleware;
use app\middleware\InstallGuardMiddleware;
use app\middleware\LocaleMiddleware;
use app\middleware\RequestContextMiddleware;
use app\middleware\TelemetryMiddleware;
use core\plugin\contracts\RouteBuckets;

final class PluginRouteBuckets implements RouteBuckets
{
    public function prefixes(): array
    {
        return ['adminapi', 'api'];
    }

    public function bucketsForPrefix(string $prefix): array
    {
        return match ($prefix) {
            'adminapi' => ['adminapi'],
            'api' => ['api', 'api_auth'],
            default => [],
        };
    }

    public function controllerDirs(): array
    {
        return [
            'adminapi' => 'app/adminapi/controller',
            'api' => 'app/api/controller',
            'api_auth' => 'app/api/controller',
        ];
    }

    public function authPairs(): array
    {
        return ['api' => 'api_auth'];
    }

    public function missingRoutesError(): string
    {
        return 'manifest.routes 不能为空';
    }

    public function chain(string $bucket, string $code, array $manifest): array
    {
        $common = [
            InstallGuardMiddleware::class,
            RequestContextMiddleware::class,
            LocaleMiddleware::class,
            TelemetryMiddleware::class,
        ];

        return match ($bucket) {
            'adminapi' => [...$common, AdminAuthMiddleware::class, AdminPermissionMiddleware::class, IdempotencyMiddleware::class, AdminLogMiddleware::class],
            'api_auth' => [...$common, ApiAuthMiddleware::class],
            'api' => $common,
            default => [],
        };
    }
}
