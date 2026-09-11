<?php

declare(strict_types=1);

namespace tests\Unit\Auth;

use core\auth\TokenVersion;
use support\Redis;
use tests\TestCase;

final class TokenVersionTest extends TestCase
{
    private const ADMIN_ID = 424242;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::del('admin_token_ver:' . self::ADMIN_ID);
    }

    protected function tearDown(): void
    {
        Redis::del('admin_token_ver:' . self::ADMIN_ID);
        parent::tearDown();
    }

    public function test_first_read_seeds_a_random_base_far_from_zero(): void
    {
        $version = TokenVersion::current(self::ADMIN_ID);

        $this->assertGreaterThanOrEqual(1_000_000, $version);
        $this->assertLessThanOrEqual(2_000_000_000, $version);
        $this->assertSame($version, TokenVersion::current(self::ADMIN_ID), '只播种一次');
    }

    public function test_bump_increments_from_the_current_version(): void
    {
        $base = TokenVersion::current(self::ADMIN_ID);

        $this->assertSame($base + 1, TokenVersion::bump(self::ADMIN_ID));
        $this->assertSame($base + 2, TokenVersion::bump(self::ADMIN_ID));
        $this->assertSame($base + 2, TokenVersion::current(self::ADMIN_ID));
    }

    public function test_bump_on_a_missing_key_seeds_before_incrementing(): void
    {
        $this->assertGreaterThan(1_000_000, TokenVersion::bump(self::ADMIN_ID), '缺键时直接 INCR 会得到 1');
    }

    public function test_losing_the_key_reseeds_a_different_version(): void
    {
        $before = TokenVersion::current(self::ADMIN_ID);
        Redis::del('admin_token_ver:' . self::ADMIN_ID);

        $this->assertNotSame($before, TokenVersion::current(self::ADMIN_ID), '旧 token 的 ver 对不上，fail closed');
    }
}
