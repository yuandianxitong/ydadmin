<?php

declare(strict_types=1);

namespace tests\RedLine;

use core\auth\TokenVersion;
use support\Cache;
use tests\Support\ApiTestCase;

/**
 * 红线：clear-cache 之后，已吊销的 token 仍然无效（spec §1.1 第 4 条、§7.2）。
 *
 * TP8 的 clear-cache 调用的是 Cache::clear()。本项目有三样东西共用同一个 Redis 库：
 *   - support\Cache；
 *   - token 黑名单（经 support\Cache 写入）；
 *   - token 版本号（经 support\Redis 写入）。
 * Symfony RedisAdapter 在无命名空间时 clear() 会清掉整个库，所以照抄 TP8 会导致：
 *   - 已登出 token 的拉黑记录消失；
 *   - 版本号被重新随机播种，所有在线管理员被踢下线；
 *   - 验证码、权限、字典缓存一并丢失。
 * 因此 clear-cache 只能清配置缓存。
 */
final class Test14_ClearCacheKeepsRevocationTest extends ApiTestCase
{
    private const CANARY = 'captcha.rl14canary';

    protected function tearDown(): void
    {
        Cache::delete(self::CANARY);
        parent::tearDown();
    }

    public function test_revoked_and_logged_out_tokens_stay_invalid_after_clear_cache(): void
    {
        $operator = $this->actingAsAdmin(); // clear-cache 是 PermissionSkip：登录即可
        $revoked = $this->actingAsAdmin();
        $loggedOut = $this->actingAsAdmin();

        // 吊销（版本号自增）与拉黑（登出）
        $version = TokenVersion::bump($revoked->id);
        $this->get('/adminapi/auth/info', [], $revoked->token)->assertCode(401);
        $this->post('/adminapi/auth/logout', [], $loggedOut->token)->assertOk();
        $this->get('/adminapi/auth/info', [], $loggedOut->token)->assertCode(401);
        Cache::set(self::CANARY, 'abcd', 300); // 与配置无关的缓存

        $this->post('/adminapi/system/config/clear-cache', [], $operator->token)->assertOk();

        $this->get('/adminapi/auth/info', [], $revoked->token)->assertCode(401);
        $this->get('/adminapi/auth/info', [], $loggedOut->token)->assertCode(401);
        $this->assertSame($version, TokenVersion::current($revoked->id), '版本号不能被清掉后重新播种');
        $this->get('/adminapi/auth/info', [], $operator->token)->assertOk(); // 操作者自己的会话与权限缓存不受影响
        $this->assertSame('abcd', Cache::get(self::CANARY), '清缓存不能波及配置以外的缓存');
    }
}
