<?php

declare(strict_types=1);

namespace tests\Unit\Realtime;

use core\realtime\ConnectionRegistry;
use tests\TestCase;

final class ConnectionRegistryTest extends TestCase
{
    private static function conn(int $id): object
    {
        return new class ($id) {
            public function __construct(public int $id)
            {
            }
        };
    }

    public function test_same_admin_can_hold_several_connections(): void
    {
        $registry = new ConnectionRegistry();
        $a = self::conn(1);
        $b = self::conn(2);
        $c = self::conn(3);
        $registry->add(10, $a);
        $registry->add(10, $b);
        $registry->add(20, $c);

        $this->assertSame(3, $registry->count());
        $this->assertSame([10, 20], $registry->adminIds());
        $this->assertSame(10, $registry->adminOf($b));
        $this->assertSame([$a, $b], $registry->forTargets([10]));
    }

    public function test_remove_returns_owner_and_drops_empty_admin(): void
    {
        $registry = new ConnectionRegistry();
        $a = self::conn(1);
        $registry->add(10, $a);

        $this->assertSame(10, $registry->remove($a));
        $this->assertNull($registry->remove($a), '重复移除返回 null');
        $this->assertSame([], $registry->adminIds());
        $this->assertNull($registry->adminOf($a));
        $this->assertSame(0, $registry->count());
    }

    public function test_for_targets_all_returns_every_connection_and_skips_unknown_admins(): void
    {
        $registry = new ConnectionRegistry();
        $a = self::conn(1);
        $b = self::conn(2);
        $registry->add(10, $a);
        $registry->add(20, $b);

        $this->assertSame([$a, $b], $registry->forTargets('all'));
        $this->assertSame([$a, $b], $registry->all());
        $this->assertSame([$b], $registry->forTargets([20, 999]));
        $this->assertSame([], $registry->forTargets([999]));
    }

    public function test_re_adding_a_connection_under_another_admin_moves_it(): void
    {
        $registry = new ConnectionRegistry();
        $a = self::conn(1);
        $registry->add(10, $a);
        $registry->add(20, $a);

        $this->assertSame([20], $registry->adminIds());
        $this->assertSame(1, $registry->count());
    }
}
