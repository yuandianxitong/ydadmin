<?php

declare(strict_types=1);

namespace tests\Unit\Auth;

use core\auth\TokenVersion;
use support\Redis;
use tests\TestCase;

final class TokenVersionTest extends TestCase
{
    private const ADMIN_ID = 424242;

    /** 与 ADMIN_ID 同号：两个 scope 的版本号必须互不影响（M5a spec §4.2） */
    private const USER_ID = 424242;

    protected function setUp(): void
    {
        parent::setUp();
        $this->forgetVersions();
    }

    protected function tearDown(): void
    {
        $this->forgetVersions();
        parent::tearDown();
    }

    private function forgetVersions(): void
    {
        Redis::del('admin_token_ver:' . self::ADMIN_ID, 'user_token_ver:' . self::USER_ID);
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

    /**
     * admin 的 key 名是 M1/M4 的既成事实：AdminService、ForceLogoutService、WebSocketServer 按它吊销，
     * ApiTestCase 与三条红线测试按它清缓存。加 scope 参数不许动它——这条一红就说明 M1/M4 被无声改坏了。
     */
    public function test_admin_key_name_is_unchanged_and_scope_defaults_to_admin(): void
    {
        $version = TokenVersion::current(self::ADMIN_ID);

        $this->assertSame((string) $version, (string) Redis::get('admin_token_ver:' . self::ADMIN_ID), 'admin 的 key 名必须仍是 admin_token_ver:{id}');
        $this->assertSame($version, TokenVersion::current(self::ADMIN_ID, 'admin'), '显式传 admin 与省略第二参数等价');
        $this->assertSame(0, (int) Redis::exists('user_token_ver:' . self::ADMIN_ID), '默认 scope 不许碰 user 的 key');
    }

    public function test_user_scope_has_its_own_key_and_does_not_disturb_admin(): void
    {
        $adminVersion = TokenVersion::current(self::ADMIN_ID);
        $userVersion = TokenVersion::current(self::USER_ID, 'user');

        $this->assertSame((string) $userVersion, (string) Redis::get('user_token_ver:' . self::USER_ID));
        $this->assertGreaterThanOrEqual(1_000_000, $userVersion, 'C 端同样从随机基数起步');
        $this->assertSame($userVersion + 1, TokenVersion::bump(self::USER_ID, 'user'));
        $this->assertSame($adminVersion, TokenVersion::current(self::ADMIN_ID), '禁用一个会员不能把同 id 的管理员踢下线');
    }
}
