<?php

declare(strict_types=1);

namespace tests\RedLine;

use app\middleware\AdminAuthMiddleware;
use app\middleware\ApiAuthMiddleware;
use core\response\Api;
use Firebase\JWT\JWT;

final class Test2_TokenScopeIsolationTest extends RedLineCase
{
    public function test_admin_token_cannot_access_api(): void
    {
        $response = (new ApiAuthMiddleware())->process($this->request('/api/rl', $this->adminToken()), fn () => Api::success());
        $this->assertSame(401, $this->code($response));
    }

    public function test_user_token_cannot_access_adminapi(): void
    {
        $response = (new AdminAuthMiddleware())->process($this->request('/adminapi/rl', $this->userToken()), fn () => Api::success());
        $this->assertSame(401, $this->code($response));
    }

    public function test_admin_claims_signed_with_user_key_are_rejected(): void
    {
        // 伪造：声明自己是 admin scope，却用 user scope 的密钥签名
        $forged = JWT::encode([
            'iss' => 'ydadmin-admin', 'iat' => time(), 'exp' => time() + 3600, 'login_at' => time(),
            'jti' => bin2hex(random_bytes(16)), 'scope' => 'admin', 'data' => ['admin_id' => 1],
        ], (string) config('auth.jwt.user.key'), 'HS256');

        $response = (new AdminAuthMiddleware())->process($this->request('/adminapi/rl', $forged), fn () => Api::success());
        $this->assertSame(401, $this->code($response));
    }

    public function test_alg_none_token_is_rejected(): void
    {
        $encode = static fn (array $part): string => rtrim(strtr(base64_encode((string) json_encode($part)), '+/', '-_'), '=');
        $unsigned = $encode(['alg' => 'none', 'typ' => 'JWT']) . '.' . $encode([
            'iss' => 'ydadmin-admin', 'iat' => time(), 'exp' => time() + 3600, 'login_at' => time(),
            'jti' => bin2hex(random_bytes(16)), 'scope' => 'admin', 'data' => ['admin_id' => 1],
        ]) . '.';

        $response = (new AdminAuthMiddleware())->process($this->request('/adminapi/rl', $unsigned), fn () => Api::success());
        $this->assertSame(401, $this->code($response));
    }
}
