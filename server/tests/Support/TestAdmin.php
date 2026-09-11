<?php

declare(strict_types=1);

namespace tests\Support;

/** actingAsAdmin() 创建的管理员：id、登录名、明文密码与已签发的 token。 */
final readonly class TestAdmin
{
    public function __construct(
        public int $id,
        public string $username,
        public string $password,
        public string $token,
    ) {
    }
}
