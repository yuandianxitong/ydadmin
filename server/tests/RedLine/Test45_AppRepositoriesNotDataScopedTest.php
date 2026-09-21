<?php

declare(strict_types=1);

namespace tests\RedLine;

use app\repository\dataimport\DataImportRepository;
use app\repository\region\RegionRepository;
use app\repository\version\AppVersionRepository;
use core\base\Repository;
use ReflectionProperty;
use tests\TestCase;

/**
 * 红线（M7b）：地区 / 版本 / 导入仓储不受数据权限约束。
 *
 * 三张表都没有 created_by 也没有部门列，必须**不重新声明** `$dataScoped`
 *（沿用基类 core\base\Repository 的默认值 false），与 Test22 / Test26 / Test41 同理。
 */
final class Test45_AppRepositoriesNotDataScopedTest extends TestCase
{
    public function test_region_version_import_repositories_do_not_redeclare_data_scoped(): void
    {
        foreach ([RegionRepository::class, AppVersionRepository::class, DataImportRepository::class] as $class) {
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
            "{$class} 不应重新声明 \$dataScoped：地区/版本/导入没有 created_by 也没有部门列"
        );
        $this->assertFalse($property->getDefaultValue(), '基类默认值应为 false');
    }
}
