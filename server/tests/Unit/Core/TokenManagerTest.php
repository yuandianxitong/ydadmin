<?php

declare(strict_types=1);

namespace tests\Unit\Core;

use core\auth\TokenManager;
use core\exception\AuthException;
use Firebase\JWT\JWT;
use support\Redis;
use tests\TestCase;

final class TokenManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TokenManager::flushInstances();
    }

    /** @return array<string, mixed> */
    private function claims(string $token): array
    {
        return json_decode((string) base64_decode(strtr(explode('.', $token)[1], '-_', '+/')), true);
    }

    public function test_claims_keep_tp8_contract(): void
    {
        $claims = $this->claims(TokenManager::scope('admin')->generate(['admin_id' => 1, 'username' => 'admin']));

        $this->assertSame(86400, $claims['exp'] - $claims['iat'], 'admin 前端按 iat/exp 计算静默刷新时机');
        $this->assertSame($claims['iat'], $claims['login_at']);
        $this->assertSame('admin', $claims['scope']);
        $this->assertSame('ydadmin-admin', $claims['iss']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $claims['jti']);
    }

    public function test_generate_verify_roundtrip(): void
    {
        $mgr = TokenManager::scope('admin');
        $this->assertSame(['admin_id' => 1, 'username' => 'admin'], $mgr->verify($mgr->generate(['admin_id' => 1, 'username' => 'admin'])));
    }

    public function test_unknown_scope_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TokenManager::scope('platform');
    }

    public function test_tokens_cannot_cross_scopes(): void
    {
        $adminToken = TokenManager::scope('admin')->generate(['admin_id' => 1]);
        $userToken = TokenManager::scope('user')->generate(['user_id' => 1]);

        foreach ([['user', $adminToken], ['admin', $userToken]] as [$scope, $token]) {
            try {
                TokenManager::scope($scope)->verify($token);
                $this->fail("{$scope} scope 不得接受另一个 scope 的 token");
            } catch (AuthException $e) {
                $this->assertSame(401, $e->getCode());
            }
        }
    }

    public function test_issuer_mismatch_is_rejected_even_with_correct_key(): void
    {
        $forged = JWT::encode([
            'iss' => 'evil', 'iat' => time(), 'exp' => time() + 60, 'login_at' => time(),
            'jti' => bin2hex(random_bytes(16)), 'scope' => 'admin', 'data' => ['admin_id' => 1],
        ], (string) config('auth.jwt.admin.key'), 'HS256');

        $this->expectException(AuthException::class);
        TokenManager::scope('admin')->verify($forged);
    }

    public function test_expired_token_is_rejected(): void
    {
        $expired = JWT::encode([
            'iss' => 'ydadmin-admin', 'iat' => time() - 100, 'exp' => time() - 10, 'login_at' => time() - 100,
            'jti' => bin2hex(random_bytes(16)), 'scope' => 'admin', 'data' => ['admin_id' => 1],
        ], (string) config('auth.jwt.admin.key'), 'HS256');

        $this->expectException(AuthException::class);
        TokenManager::scope('admin')->verify($expired);
    }

    public function test_tampered_and_garbage_tokens_are_rejected(): void
    {
        $token = TokenManager::scope('admin')->generate(['admin_id' => 1]);
        $parts = explode('.', $token);
        $parts[2] = strrev($parts[2]);

        foreach ([implode('.', $parts), 'not-a-jwt', ''] as $bad) {
            try {
                TokenManager::scope('admin')->verify($bad);
                $this->fail('应拒绝：' . $bad);
            } catch (AuthException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_blacklisted_token_is_rejected(): void
    {
        $mgr = TokenManager::scope('admin');
        $token = $mgr->generate(['admin_id' => 1]);
        $mgr->blacklist($token);

        $this->expectException(AuthException::class);
        $mgr->verify($token);
    }

    public function test_blacklisting_invalid_token_is_silent(): void
    {
        TokenManager::scope('admin')->blacklist('not-a-jwt');
        $this->addToAssertionCount(1);
    }

    public function test_refresh_issues_new_token_keeps_login_at_and_revokes_old(): void
    {
        $mgr = TokenManager::scope('admin');
        $old = $mgr->generate(['admin_id' => 1], time() - 100);
        $new = $mgr->refresh($old);

        $this->assertSame(['admin_id' => 1], $mgr->verify($new));
        $this->assertSame($this->claims($old)['login_at'], $this->claims($new)['login_at']);

        $this->expectException(AuthException::class);
        $mgr->verify($old);
    }

    public function test_refresh_beyond_seven_days_requires_login(): void
    {
        $mgr = TokenManager::scope('admin');
        $token = $mgr->generate(['admin_id' => 1], time() - 604801);

        try {
            $mgr->refresh($token);
            $this->fail('超过 7 天绝对上限必须重新登录');
        } catch (AuthException $e) {
            $this->assertSame(lang('auth.login_expired'), $e->getMessage());
        }
    }

    public function test_token_from_header(): void
    {
        $mgr = TokenManager::scope('admin');
        $with = new \Webman\Http\Request("GET / HTTP/1.1\r\nHost: localhost\r\nAuthorization: Bearer abc.def.ghi\r\n\r\n");
        $without = new \Webman\Http\Request("GET / HTTP/1.1\r\nHost: localhost\r\n\r\n");

        $this->assertSame('abc.def.ghi', $mgr->getTokenFromHeader($with));
        $this->assertNull($mgr->getTokenFromHeader($without));
    }

    public function test_short_secret_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('32');
        TokenManager::assertKeyStrength('admin', str_repeat('a', 31));
    }

    public function test_missing_secret_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        TokenManager::assertKeyStrength('admin', '');
    }

    public function test_concurrent_refresh_of_one_token_only_succeeds_once(): void
    {
        $mgr = TokenManager::scope('admin');
        $token = $mgr->generate(['admin_id' => 9, 'username' => 'race']);
        $claims = json_decode((string) base64_decode(strtr(explode('.', $token)[1], '-_', '+/')), true);
        // 模拟另一个并发请求已抢到刷新锁
        Redis::setex("refresh_lock.admin.{$claims['jti']}", 60, '1');

        $this->expectException(AuthException::class);
        $mgr->refresh($token);
    }

    public function test_refresh_applies_payload_overrides(): void
    {
        $mgr = TokenManager::scope('admin');
        $token = $mgr->generate(['admin_id' => 9, 'username' => 'race', 'ver' => 0]);
        $new = $mgr->refresh($token, ['ver' => 0, 'username' => 'renamed']);

        $this->assertSame('renamed', $mgr->verify($new)['username']);
    }

    public function test_refresh_rejects_a_token_whose_version_was_bumped(): void
    {
        $mgr = TokenManager::scope('admin');
        $token = $mgr->generate(['admin_id' => 9, 'username' => 'race', 'ver' => 0]);

        $this->expectException(AuthException::class);
        $mgr->refresh($token, ['ver' => 1]);
    }
}
