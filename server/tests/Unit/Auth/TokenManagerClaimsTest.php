<?php

declare(strict_types=1);

namespace tests\Unit\Auth;

use core\auth\TokenManager;
use core\exception\AuthException;
use Firebase\JWT\JWT;
use tests\TestCase;

final class TokenManagerClaimsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TokenManager::flushInstances();
    }

    public function test_verify_claims_returns_payload_jti_and_exp(): void
    {
        $manager = TokenManager::scope('admin');
        $token = $manager->generate(['admin_id' => 5, 'ver' => 7]);

        $claims = $manager->verifyClaims($token);

        $this->assertSame(['admin_id' => 5, 'ver' => 7], $claims['payload']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $claims['jti']);
        $this->assertGreaterThan(time(), $claims['exp']);
        $this->assertSame($claims['payload'], $manager->verify($token), 'verify() 的返回值保持不变');
    }

    public function test_is_jti_revoked_follows_the_blacklist(): void
    {
        $manager = TokenManager::scope('admin');
        $token = $manager->generate(['admin_id' => 5]);
        $jti = $manager->verifyClaims($token)['jti'];

        $this->assertFalse($manager->isJtiRevoked($jti));
        $manager->blacklist($token);
        $this->assertTrue($manager->isJtiRevoked($jti));

        $this->expectException(AuthException::class);
        $manager->verifyClaims($token);
    }

    public function test_verify_claims_exposes_the_absolute_session_expiry_from_login_at(): void
    {
        $manager = TokenManager::scope('admin');
        $loginAt = time() - 100;

        $claims = $manager->verifyClaims($manager->generate(['admin_id' => 5], $loginAt));

        $this->assertSame($loginAt + 604800, $claims['session_expires_at'], 'login_at + refresh_expire（7 天绝对上限）');
    }

    public function test_session_expiry_falls_back_to_exp_when_login_at_is_absent(): void
    {
        $config = (array) (config('auth', [])['jwt'] ?? []);
        $exp = time() + 600;
        $token = JWT::encode([
            'iss'   => 'ydadmin-admin',
            'iat'   => time(),
            'exp'   => $exp,
            'jti'   => bin2hex(random_bytes(16)),
            'scope' => 'admin',
            'data'  => ['admin_id' => 5],
        ], (string) $config['admin']['key'], 'HS256');

        $this->assertSame($exp, TokenManager::scope('admin')->verifyClaims($token)['session_expires_at']);
    }

    public function test_session_id_in_the_payload_survives_refresh_and_is_not_revoked_by_it(): void
    {
        $manager = TokenManager::scope('admin');
        $sid = bin2hex(random_bytes(16));
        $old = $manager->generate(['admin_id' => 5, 'ver' => 0, 'sid' => $sid]);

        $new = $manager->refresh($old, ['ver' => 0]);

        $this->assertSame($sid, $manager->verify($new)['sid'] ?? null, '刷新沿用同一个会话 id');
        $this->assertFalse($manager->isSessionRevoked($sid), '刷新只拉黑旧 jti，不吊销会话');
    }

    public function test_revoke_session_marks_the_sid_revoked_in_its_own_scope_only(): void
    {
        $manager = TokenManager::scope('admin');
        $sid = bin2hex(random_bytes(16));
        $token = $manager->generate(['admin_id' => 5, 'sid' => $sid]);

        $this->assertFalse($manager->isSessionRevoked($sid));
        $manager->revokeSession($token);

        $this->assertTrue($manager->isSessionRevoked($sid));
        $this->assertFalse(TokenManager::scope('user')->isSessionRevoked($sid), '会话吊销按 scope 隔离');
    }

    public function test_revoke_session_ignores_tokens_without_a_sid_and_invalid_tokens(): void
    {
        $manager = TokenManager::scope('admin');

        $manager->revokeSession($manager->generate(['admin_id' => 5]));
        $manager->revokeSession('not-a-jwt');

        $this->assertFalse($manager->isSessionRevoked(''), '空 sid 永不视为已吊销');
    }

    public function test_exp_is_clamped_to_the_absolute_session_end(): void
    {
        $manager = TokenManager::scope('admin');
        $loginAt = time() - 604800 + 30;

        $claims = $manager->verifyClaims($manager->generate(['admin_id' => 5], $loginAt));

        $this->assertLessThanOrEqual($loginAt + 604800, $claims['exp']);
        $this->assertGreaterThan(time(), $claims['exp']);
    }

    public function test_a_revoked_session_cannot_be_used_or_refreshed(): void
    {
        $manager = TokenManager::scope('admin');
        $sid = bin2hex(random_bytes(16));
        $token = $manager->generate(['admin_id' => 5, 'ver' => 0, 'sid' => $sid]);
        $manager->revokeSession($token);

        try {
            $manager->verify($token);
            $this->fail('已登出的会话不能再通过校验');
        } catch (AuthException) {
            $this->addToAssertionCount(1);
        }
        try {
            $manager->refresh($token, ['ver' => 0]);
            $this->fail('已登出的会话不能换发新 token');
        } catch (AuthException) {
            $this->addToAssertionCount(1);
        }
    }
}
