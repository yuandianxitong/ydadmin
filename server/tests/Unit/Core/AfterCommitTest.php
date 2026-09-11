<?php

declare(strict_types=1);

namespace tests\Unit\Core;

use core\base\Service;
use tests\TestCase;

final class AfterCommitProbeService extends Service
{
    public function txWithHook(\Closure $hook): void
    {
        $this->runInTransaction(function () use ($hook) {
            $this->afterCommit($hook);
        });
    }

    public function nestedTxWithHooks(\Closure $innerHook, \Closure $outerHook): void
    {
        $this->runInTransaction(function () use ($innerHook, $outerHook) {
            $this->runInTransaction(function () use ($innerHook) {
                $this->afterCommit($innerHook);
            });
            $this->afterCommit($outerHook);
        });
    }

    public function hookOutsideTx(\Closure $hook): void
    {
        $this->afterCommit($hook);
    }

    public function rolledBackHook(\Closure $hook): void
    {
        try {
            $this->runInTransaction(function () use ($hook) {
                $this->afterCommit($hook);
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException) {
        }
    }
}

final class AfterCommitTest extends TestCase
{
    public function test_hook_fires_after_commit(): void
    {
        $fired = false;
        (new AfterCommitProbeService())->txWithHook(function () use (&$fired) {
            $fired = true;
        });
        $this->assertTrue($fired);
    }

    public function test_hook_outside_transaction_fires_immediately(): void
    {
        $fired = false;
        (new AfterCommitProbeService())->hookOutsideTx(function () use (&$fired) {
            $fired = true;
        });
        $this->assertTrue($fired);
    }

    public function test_inner_hook_waits_for_outer_commit(): void
    {
        $order = [];
        (new AfterCommitProbeService())->nestedTxWithHooks(
            function () use (&$order) {
                $order[] = 'inner';
            },
            function () use (&$order) {
                $order[] = 'outer';
            }
        );
        $this->assertSame(['inner', 'outer'], $order);
    }

    public function test_rollback_discards_hook(): void
    {
        $fired = false;
        (new AfterCommitProbeService())->rolledBackHook(function () use (&$fired) {
            $fired = true;
        });
        $this->assertFalse($fired, '回滚必须丢弃 afterCommit 回调');
    }

    public function test_discarded_hook_does_not_leak_into_next_transaction(): void
    {
        $leaked = false;
        $svc = new AfterCommitProbeService();
        $svc->rolledBackHook(function () use (&$leaked) {
            $leaked = true;
        });
        $next = false;
        $svc->txWithHook(function () use (&$next) {
            $next = true;
        });
        $this->assertFalse($leaked);
        $this->assertTrue($next);
    }
}
