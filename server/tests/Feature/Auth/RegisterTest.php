<?php
declare(strict_types=1);

namespace tests\Feature\Auth;

use tests\TestCase;
use app\service\user\UserService;
use core\exception\BusinessException;

class RegisterTest extends TestCase
{
    private UserService $userService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userService = $this->getService(UserService::class);
    }

    public function testRegisterWithValidData(): void
    {
        $mobile = '138' . str_pad((string) mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT);

        $result = $this->userService->register($mobile, 'Test@123456');

        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('user_info', $result);
        $this->assertNotEmpty($result['token']);
        $this->assertEquals($mobile, $result['user_info']['mobile']);
    }

    public function testRegisterDuplicateMobile(): void
    {
        $mobile = '13900000001';

        // First registration
        try {
            $this->userService->register($mobile, 'Test@123456');
        } catch (\Exception) {
            // May already exist from a previous test run
        }

        // Second registration should fail with BusinessException
        $this->expectException(BusinessException::class);
        $this->userService->register($mobile, 'Test@123456');
    }

    public function testRegisterReturnsValidToken(): void
    {
        $mobile = '139' . str_pad((string) mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT);

        $result = $this->userService->register($mobile, 'SecurePass@789');

        // Token should be a non-empty JWT string (three dot-separated parts)
        $this->assertNotEmpty($result['token']);
        $parts = explode('.', $result['token']);
        $this->assertCount(3, $parts, 'Token should be a valid JWT with 3 parts');
    }
}
