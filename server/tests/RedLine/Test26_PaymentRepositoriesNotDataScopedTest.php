<?php

declare(strict_types=1);

namespace tests\RedLine;

use app\repository\payment\PaymentOrderRepository;
use app\repository\payment\RefundOrderRepository;
use core\base\Repository;
use ReflectionProperty;
use tests\TestCase;

/**
 * 红线（M5b spec §8「数据权限」）：payment_orders / refund_orders 不受数据权限约束，且是**不声明**
 * `$dataScoped`（沿用基类 core\base\Repository 的默认值 false），与 Test22 同理。
 *
 * 这两张表没有 created_by、也没有部门列：C 端按 user_id 显式过滤，关单与对账在命令行里跑、没有管理员
 * 上下文。一旦声明 `$dataScoped`，M7 的管理端订单页会按不存在的列过滤；用反射钉住「没有重新声明」，
 * 比只检查「当前值是 false」更严格。
 */
final class Test26_PaymentRepositoriesNotDataScopedTest extends TestCase
{
    public function test_payment_order_repository_does_not_redeclare_data_scoped(): void
    {
        $this->assertNotDataScoped(PaymentOrderRepository::class);
    }

    public function test_refund_order_repository_does_not_redeclare_data_scoped(): void
    {
        $this->assertNotDataScoped(RefundOrderRepository::class);
    }

    /** @param class-string $class */
    private function assertNotDataScoped(string $class): void
    {
        $property = new ReflectionProperty($class, 'dataScoped');
        $this->assertSame(
            Repository::class,
            $property->getDeclaringClass()->getName(),
            "{$class} 不应重新声明 \$dataScoped：payment_orders/refund_orders 没有 created_by 也没有部门列（M5b spec §8）"
        );
        $this->assertFalse($property->getDefaultValue(), '基类默认值应为 false');
    }
}
