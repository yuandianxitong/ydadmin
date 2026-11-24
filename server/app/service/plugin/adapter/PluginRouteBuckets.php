<?php

declare(strict_types=1);

namespace app\service\plugin\adapter;

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
            'api' => ['api'],
            default => [],
        };
    }

    public function controllerDirs(): array
    {
        return [
            'adminapi' => 'app/adminapi/controller',
            'api' => 'app/api/controller',
        ];
    }

    public function authPairs(): array
    {
        return [];
    }

    public function missingRoutesError(): string
    {
        return 'manifest.routes 不能为空';
    }

    public function chain(string $bucket, string $code, array $manifest): array
    {
        return [];
    }
}
