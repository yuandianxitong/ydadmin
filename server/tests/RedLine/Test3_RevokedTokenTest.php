<?php

declare(strict_types=1);

namespace tests\RedLine;

use app\middleware\AdminAuthMiddleware;
use core\auth\TokenManager;
use core\response\Api;

final class Test3_RevokedTokenTest extends RedLineCase
{
    private function tokenStatus(string $token): int
    {
        return $this->code((new AdminAuthMiddleware())->process($this->request('/adminapi/rl', $token), fn () => Api::success()));
    }

    public function test_logged_out_token_is_rejected(): void
    {
        $token = $this->adminToken();
        $this->assertSame(200, $this->tokenStatus($token));

        TokenManager::scope('admin')->blacklist($token);
        $this->assertSame(401, $this->tokenStatus($token));
    }

    public function test_token_replaced_by_refresh_is_rejected(): void
    {
        $old = $this->adminToken();
        $new = TokenManager::scope('admin')->refresh($old);

        $this->assertSame(401, $this->tokenStatus($old));
        $this->assertSame(200, $this->tokenStatus($new));
    }
}
