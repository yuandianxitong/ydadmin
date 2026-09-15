<?php

declare(strict_types=1);

namespace tests\Unit\Auth;

use core\auth\TokenManager;
use core\exception\AuthException;
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
}
