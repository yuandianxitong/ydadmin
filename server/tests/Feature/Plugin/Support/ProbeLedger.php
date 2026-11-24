<?php

declare(strict_types=1);

namespace tests\Feature\Plugin\Support;

/**
 * 测试专用：记录夹具插件 Plugin\Probe\InstallHook 的调用次数。
 * 计数放在钩子类之外，测试才不必在安装前预加载钩子类，自动加载器的登记才真正被走到。
 */
final class ProbeLedger
{
    public static int $installs = 0;

    public static int $enables = 0;

    public static int $disables = 0;

    public static function reset(): void
    {
        self::$installs = 0;
        self::$enables = 0;
        self::$disables = 0;
    }
}
