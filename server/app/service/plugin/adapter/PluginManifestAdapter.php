<?php

declare(strict_types=1);

namespace app\service\plugin\adapter;

use core\plugin\contracts\ManifestExtension;

/** 站点端没有宿主专有的清单字段。 */
final class PluginManifestAdapter implements ManifestExtension
{
    public function validate(array $manifest): array
    {
        return [];
    }

    public function normalize(array $manifest): array
    {
        return $manifest;
    }
}
