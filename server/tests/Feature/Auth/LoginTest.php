<?php
declare(strict_types=1);

namespace tests\Feature\Auth;

use tests\TestCase;
use app\service\user\UserService;
use core\auth\TokenManager;
use core\exception\BusinessException;
use core\exception\AuthException;

class LoginTest extends TestCase
{
    private UserService $userService;
    private TokenManager $tokenManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userService = $this->getService(UserService::class);
        $this->tokenManager = $this->getService(TokenManager::class);
    }

    public function testLoginWithValidCredentials(): void
    {
        // This test requires a seeded user in the database
        // Seed: mobile=13800138000, password=123456
        $result = $this->userService->loginByPassword('13800138000', '123456');

        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('user_info', $result);
        $this->assertNotEmpty($result['token']);
    }

    public function testLoginWithInvalidPassword(): void
    {
        $this->expectException(BusinessException::class);
        $this->userService->loginByPassword('13800138000', 'wrong_password');
    }

    public function testTokenGeneration(): void
    {
        $payload = ['type' => 'user', 'user_id' => 1];
        $token = $this->tokenManager->generate($payload);

        $this->assertNotEmpty($token);
        $this->assertIsString($token);
    }

    public function testTokenVerification(): void
    {
        $payload = ['type' => 'user', 'user_id' => 1];
        $token = $this->tokenManager->generate($payload);

        $decoded = $this->tokenManager->verify($token);
        $this->assertEquals(1, $decoded['user_id']);
        $this->assertEquals('user', $decoded['type']);
    }

    public function testTokenRefresh(): void
    {
        $payload = ['type' => 'user', 'user_id' => 1];
        $token = $this->tokenManager->generate($payload);

        $newToken = $this->tokenManager->refresh($token);

        $this->assertNotEmpty($newToken);
        $this->assertNotEquals($token, $newToken);

        // Verify the new token contains the same payload
        $decoded = $this->tokenManager->verify($newToken);
        $this->assertEquals(1, $decoded['user_id']);
        $this->assertEquals('user', $decoded['type']);
    }

    public function testBlacklistedTokenIsRejected(): void
    {
        $payload = ['type' => 'user', 'user_id' => 1];
        $token = $this->tokenManager->generate($payload);

        // Blacklist the token
        $this->tokenManager->blacklist($token);

        // Old token should be rejected
        $this->expectException(AuthException::class);
        $this->tokenManager->verify($token);
    }

    public function testGetUserIdFromToken(): void
    {
        $payload = ['type' => 'user', 'user_id' => 42];
        $token = $this->tokenManager->generate($payload);

        $userId = $this->tokenManager->getUserId($token);
        $this->assertEquals(42, $userId);
    }
}
