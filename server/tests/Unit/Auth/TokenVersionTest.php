<?php

declare(strict_types=1);

namespace tests\Unit\Auth;

use core\auth\TokenVersion;
use support\Redis;
use tests\TestCase;

final class TokenVersionTest extends TestCase
{
    protected function tearDown(): void
    {
        Redis::del('admin_token_ver:424242');
        parent::tearDown();
    }

    public function test_version_starts_at_zero_and_bumps(): void
    {
        $this->assertSame(0, TokenVersion::current(424242));
        $this->assertSame(1, TokenVersion::bump(424242));
        $this->assertSame(2, TokenVersion::bump(424242));
        $this->assertSame(2, TokenVersion::current(424242));
    }
}
