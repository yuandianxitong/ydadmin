<?php

declare(strict_types=1);

namespace tests\RedLine;

use app\repository\diy\DiyLinkRepository;
use app\repository\diy\DiyPageRepository;
use app\repository\diy\DiyPageVersionRepository;
use app\repository\mobile\MobileConfigRepository;
use core\base\Repository;
use ReflectionProperty;
use tests\TestCase;

/**
 * 红线（M7c）：装修页 / 版本 / 链接库 / 移动端配置仓储不受数据权限约束。
 *
 * 装修页、链接库、移动端配置没有 created_by 也没有部门列；版本表的 created_by 是操作人，不是数据归属。
 * 必须**不重新声明** `$dataScoped`（沿用基类 core\base\Repository 的默认值 false），与 Test22 / Test26 / Test45 同理。
 */
final class Test49_DiyRepositoriesNotDataScopedTest extends TestCase
{
    public function test_diy_and_mobile_config_repositories_do_not_redeclare_data_scoped(): void
    {
        foreach ([
            DiyPageRepository::class,
            DiyPageVersionRepository::class,
            DiyLinkRepository::class,
            MobileConfigRepository::class,
        ] as $class) {
            $this->assertNotRedeclared($class);
        }
    }

    /** @param class-string $class */
    private function assertNotRedeclared(string $class): void
    {
        $property = new ReflectionProperty($class, 'dataScoped');
        $this->assertSame(
            Repository::class,
            $property->getDeclaringClass()->getName(),
            "{$class} 不应重新声明 \$dataScoped：装修/移动端配置没有数据归属列"
        );
        $this->assertFalse($property->getDefaultValue(), '基类默认值应为 false');
    }
}
