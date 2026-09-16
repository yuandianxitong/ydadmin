<?php

declare(strict_types=1);

namespace tests\Support;

/** actingAsUser() 创建的 C 端会员：id、手机号、明文密码与已签发的 token。 */
final readonly class TestUser
{
    public function __construct(
        public int $id,
        public string $mobile,
        public string $password,
        public string $token,
    ) {
    }
}
