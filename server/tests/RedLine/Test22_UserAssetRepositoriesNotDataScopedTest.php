<?php

declare(strict_types=1);

namespace tests\RedLine;

use app\repository\user\BalanceLogRepository;
use app\repository\user\PointsLogRepository;
use app\repository\user\UserRepository;
use core\base\Repository;
use ReflectionProperty;
use tests\TestCase;

/**
 * 红线（spec §8「数据权限」）：users / balance_logs / points_logs 三张表不受数据权限约束，且是
 * **不声明** `$dataScoped`（用基类 core\base\Repository 的默认值 false），不是像 FileRepository
 * 那样显式写一遍 `= false`。
 *
 * 理由很实在：这三张表没有 created_by、也没有部门列。一旦有人给它们的 Repository 加上
 * `$dataScoped`，管理端会员列表会按不存在的列过滤（轻则全空、重则 SQL 报错）；而 C 端请求没有
 * 管理员上下文，问题只在管理端暴露，容易漏测。用反射钉住「压根没有重新声明这个属性」，比只检查
 * 「当前值是 false」更严格——后者挡不住有人手滑写成 `$dataScoped = false;` 之后又在别处切换。
 */
final class Test22_UserAssetRepositoriesNotDataScopedTest extends TestCase
{
    public function test_user_repository_does_not_redeclare_data_scoped(): void
    {
        $this->assertNotDataScoped(UserRepository::class);
    }

    public function test_balance_log_repository_does_not_redeclare_data_scoped(): void
    {
        $this->assertNotDataScoped(BalanceLogRepository::class);
    }

    public function test_points_log_repository_does_not_redeclare_data_scoped(): void
    {
        $this->assertNotDataScoped(PointsLogRepository::class);
    }

    /** @param class-string $class */
    private function assertNotDataScoped(string $class): void
    {
        $property = new ReflectionProperty($class, 'dataScoped');
        $this->assertSame(
            Repository::class,
            $property->getDeclaringClass()->getName(),
            "{$class} 不应重新声明 \$dataScoped：users/balance_logs/points_logs 没有 created_by 也没有部门列，"
            . '一旦声明会让管理端按不存在的列过滤数据权限（spec §8）'
        );
        $this->assertFalse($property->getDefaultValue(), '基类默认值应为 false（校验反射拿到的确实是未受控状态）');
    }
}
