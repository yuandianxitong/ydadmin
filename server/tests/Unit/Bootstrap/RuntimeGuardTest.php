<?php

declare(strict_types=1);

namespace tests\Unit\Bootstrap;

use app\bootstrap\RuntimeGuard;
use tests\TestCase;

final class RuntimeGuardTest extends TestCase
{
    public function test_default_and_non_coroutine_event_loops_are_supported(): void
    {
        foreach (['', 'Workerman\\Events\\Select', 'Workerman\\Events\\Event', 'Workerman\\Events\\Ev'] as $loop) {
            RuntimeGuard::assertSupported($loop);
        }
        $this->addToAssertionCount(1);
    }

    public function test_coroutine_event_loops_are_rejected(): void
    {
        foreach (['Workerman\\Events\\Swoole', 'Workerman\\Events\\Swow', 'Workerman\\Events\\Fiber'] as $loop) {
            try {
                RuntimeGuard::assertSupported($loop);
                $this->fail("应拒绝 {$loop}");
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('协程', $e->getMessage());
            }
        }
    }
}
