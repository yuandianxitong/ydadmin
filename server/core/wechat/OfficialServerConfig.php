<?php

declare(strict_types=1);

namespace core\wechat;

final readonly class OfficialServerConfig
{
    public function __construct(
        public string $appId,
        public string $token,
        public string $aesKey,
        public int $encryptType,
    ) {
    }
}
