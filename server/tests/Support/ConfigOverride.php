<?php

declare(strict_types=1);

namespace tests\Support;

use Webman\Config;

/**
 * 临时改写 webman 配置并在用例结束时还原。
 *
 * Webman\Config 只有 load / clear，没有 set，所以这里用反射改它的 protected static $config，
 * 并清掉 $flatCache——Config::get() 先读 flatCache，不清的话改了等于没改。
 * 只在测试里这么做：app/、core/ 禁止可变静态属性，tests/ 不在 check:context 的扫描范围内。
 */
trait ConfigOverride
{
    /** @var array<string, mixed> 改过的键 → 原值 */
    private array $configBackup = [];

    private function overrideConfig(string $key, mixed $value): void
    {
        if (!array_key_exists($key, $this->configBackup)) {
            $this->configBackup[$key] = config($key);
        }
        self::writeConfig($key, $value);
    }

    private function restoreConfig(): void
    {
        foreach ($this->configBackup as $key => $value) {
            self::writeConfig($key, $value);
        }
        $this->configBackup = [];
    }

    private static function writeConfig(string $key, mixed $value): void
    {
        $property = new \ReflectionProperty(Config::class, 'config');
        /** @var array<string, mixed> $config */
        $config = $property->getValue();

        $segments = explode('.', $key);
        $last = (string) array_pop($segments);
        $cursor = &$config;
        foreach ($segments as $segment) {
            if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }
            $cursor = &$cursor[$segment];
        }
        $cursor[$last] = $value;
        unset($cursor);

        $property->setValue(null, $config);
        (new \ReflectionProperty(Config::class, 'flatCache'))->setValue(null, []);
    }
}
