<?php

declare(strict_types=1);

namespace core\payment\config;

/**
 * 微信支付 v3 驱动配置（M5b spec §6），由 PaymentManager 从 payment 组配置组装。
 *
 * merchantSerialNo 是**商户** API 证书序列号（不是平台证书）。publicKeyId 与 publicKey 都填时走微信支付公钥模式，
 * 否则按需下载平台证书，缓存到 certCacheDir/{mchId}/（按商户号隔离）。
 */
final readonly class WechatPayConfig
{
    public function __construct(
        public string $appId,
        public string $mchId,
        public string $apiV3Key,
        public string $merchantSerialNo,
        public string $privateKeyPath,
        public ?string $publicKeyId,
        public ?string $publicKey,
        public string $certCacheDir,
        public int $certRefreshInterval,
        public float $connectTimeout,
        public float $timeout,
    ) {
    }

    public function usesPublicKey(): bool
    {
        return $this->publicKeyId !== null && $this->publicKeyId !== ''
            && $this->publicKey !== null && $this->publicKey !== '';
    }
}
