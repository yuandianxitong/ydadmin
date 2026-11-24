<?php

declare(strict_types=1);

namespace core\plugin;

use core\plugin\contracts\PluginHostProfile;

/**
 * 静态入口取宿主画像的唯一通道。
 *
 * PluginAssetPublisher、HostSdkCompat、HostSdkSpecifiers 都是静态类，拿不到构造注入，只能向容器要。
 * 容器没绑画像时抛 RuntimeException，不回退成某份内置名单。
 */
final class PluginHostProfiles
{
    public static function get(): PluginHostProfile
    {
        return self::fromLoader(static fn () => \support\Container::get(PluginHostProfile::class));
    }

    public static function fromLoader(callable $loader): PluginHostProfile
    {
        try {
            $profile = $loader();
        } catch (\Throwable $e) {
            throw new \RuntimeException('PluginHostProfile is not bound', 0, $e);
        }

        if (!$profile instanceof PluginHostProfile) {
            throw new \RuntimeException('PluginHostProfile is not bound');
        }

        return $profile;
    }
}
