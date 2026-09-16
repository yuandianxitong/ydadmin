<?php

declare(strict_types=1);

namespace tests\Support\Payment;

use core\payment\config\WechatPayConfig;
use core\payment\dto\NotifyRequest;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use WeChatPay\Crypto\AesGcm;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Formatter;

/**
 * 微信支付驱动的离线测试夹具：每个实例一套独立的商户密钥、平台密钥与自签平台证书、APIv3 key，
 * 全部写在唯一临时目录里，cleanup() 整个删掉。不提交任何 PEM 文件。
 *
 * 「平台」一侧同一对密钥兼作两种模式：
 *   - 证书模式：平台证书 $platformCertPem，序列号 $platformSerial，应答头 Wechatpay-Serial = 序列号；
 *   - 公钥模式：平台公钥 $platformPublicPem，应答头 Wechatpay-Serial = PUBLIC_KEY_ID。
 */
final class WechatPayFixture
{
    public const APP_ID = 'wx8888888888888888';

    public const PUBLIC_KEY_ID = 'PUB_KEY_ID_0114232134912410000000000000';

    public readonly string $dir;

    public readonly string $mchId;

    public readonly string $apiV3Key;

    public readonly string $merchantSerial;

    public readonly string $merchantPrivatePem;

    public readonly string $merchantPublicPem;

    public readonly string $merchantKeyPath;

    public readonly string $platformPrivatePem;

    public readonly string $platformPublicPem;

    public readonly string $platformCertPem;

    public readonly string $platformSerial;

    public function __construct()
    {
        $this->dir = PaymentKeys::tempDir();
        $this->mchId = '1900' . random_int(100000, 999999);
        $this->apiV3Key = substr(hash('sha256', 'm5b-wechatpay-fixture-' . $this->mchId), 0, 32);

        $merchant = PaymentKeys::rsaPair();
        $this->merchantPrivatePem = $merchant['private'];
        $this->merchantPublicPem = $merchant['public'];
        $this->merchantKeyPath = $this->dir . '/apiclient_key.pem';
        file_put_contents($this->merchantKeyPath, $this->merchantPrivatePem);
        $this->merchantSerial = strtoupper(dechex(self::randomSerial()));

        $platform = PaymentKeys::rsaPair();
        $this->platformPrivatePem = $platform['private'];
        $this->platformPublicPem = $platform['public'];
        $cert = PaymentKeys::selfSignedCert($this->platformPrivatePem, self::randomSerial());
        $this->platformCertPem = $cert['cert'];
        $this->platformSerial = $cert['serial'];
    }

    /**
     * 证书序列号：落在 int 范围内、首字节最高位为 0（openssl 输出的 serialNumberHex 不会带前导 0）。
     * 返回 int 是因为 openssl_csr_sign 第 6 参只收 int；需要十六进制写法时用 selfSignedCert() 返回的 serial。
     */
    public static function randomSerial(): int
    {
        return random_int(0x10000000, 0x7FFFFFFF);
    }

    public function certCacheDir(): string
    {
        return $this->dir . '/certs';
    }

    /** @param array<string, mixed> $overrides 按 WechatPayConfig 构造参数名覆盖 */
    public function config(bool $publicKeyMode = false, array $overrides = []): WechatPayConfig
    {
        $values = array_merge([
            'appId'               => self::APP_ID,
            'mchId'               => $this->mchId,
            'apiV3Key'            => $this->apiV3Key,
            'merchantSerialNo'    => $this->merchantSerial,
            'privateKeyPath'      => $this->merchantKeyPath,
            'publicKeyId'         => $publicKeyMode ? self::PUBLIC_KEY_ID : null,
            'publicKey'           => $publicKeyMode ? $this->platformPublicPem : null,
            'certCacheDir'        => $this->certCacheDir(),
            'certRefreshInterval' => 60,
            'connectTimeout'      => 5.0,
            'timeout'             => 10.0,
        ], $overrides);

        return new WechatPayConfig(...$values);
    }

    /** 把证书写进驱动会读的缓存位置 {certCacheDir}/{mchId}/{SERIAL}.pem */
    public function seedCertCache(?string $certPem = null, ?string $serial = null, ?string $mchId = null): void
    {
        $dir = $this->certCacheDir() . '/' . ($mchId ?? $this->mchId);
        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }
        file_put_contents($dir . '/' . ($serial ?? $this->platformSerial) . '.pem', $certPem ?? $this->platformCertPem);
    }

    /**
     * @param list<\Psr\Http\Message\ResponseInterface|\Throwable> $queue
     * @param array<int, array<string, mixed>>                     $history Middleware::history 的容器（引用）
     */
    public function handler(array $queue, array &$history): HandlerStack
    {
        $stack = HandlerStack::create(new MockHandler($queue));
        $stack->push(Middleware::history($history));

        return $stack;
    }

    /** 按微信规则对应答签名：timestamp\nnonce\nbody\n，默认用平台私钥、证书模式序列号 */
    public function signedResponse(int $status, string $body, ?string $serial = null, ?string $signingPrivatePem = null, ?int $timestamp = null): Response
    {
        $ts = (string) ($timestamp ?? time());
        $nonce = Formatter::nonce();
        $signature = Rsa::sign(Formatter::response($ts, $nonce, $body), $signingPrivatePem ?? $this->platformPrivatePem);

        $headers = [
            'Wechatpay-Timestamp' => $ts,
            'Wechatpay-Nonce'     => $nonce,
            'Wechatpay-Serial'    => $serial ?? $this->platformSerial,
            'Wechatpay-Signature' => $signature,
        ];
        if ($body !== '') {
            $headers['Content-Type'] = 'application/json';
        }

        return new Response($status, $headers, $body);
    }

    /** @param array<string, mixed> $payload */
    public function jsonResponse(int $status, array $payload, bool $publicKeyMode = false): Response
    {
        return $this->signedResponse(
            $status,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $publicKeyMode ? self::PUBLIC_KEY_ID : $this->platformSerial,
        );
    }

    /** 未签名的 4xx / 5xx 错误应答（微信的错误应答不经 SDK 验签，见计划 Task 4 异议 4） */
    public function errorResponse(int $status, string $code, string $message = 'error'): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode(['code' => $code, 'message' => $message], JSON_THROW_ON_ERROR));
    }

    /**
     * v3/certificates 应答：证书用 APIv3 key 做 AES-256-GCM 加密；应答本身用证书对应的私钥签名，
     * 头部序列号为 $claimedSerial（默认即证书真实序列号）——与真实微信「新证书签自己的下发应答」一致。
     */
    public function certificatesResponse(?string $certPem = null, ?string $signingPrivatePem = null, ?string $claimedSerial = null): Response
    {
        $certPem ??= $this->platformCertPem;
        $serial = $claimedSerial ?? $this->platformSerial;
        $nonce = Formatter::nonce(12);
        $aad = 'certificate';

        $body = json_encode([
            'data' => [[
                'serial_no'           => $serial,
                'effective_time'      => '2026-01-01T00:00:00+08:00',
                'expire_time'         => '2031-01-01T00:00:00+08:00',
                'encrypt_certificate' => [
                    'algorithm'       => 'AEAD_AES_256_GCM',
                    'nonce'           => $nonce,
                    'associated_data' => $aad,
                    'ciphertext'      => AesGcm::encrypt($certPem, $this->apiV3Key, $nonce, $aad),
                ],
            ]],
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return $this->signedResponse(200, $body, $serial, $signingPrivatePem ?? $this->platformPrivatePem);
    }

    /**
     * 一笔支付成功的交易（解密后的 resource 明文）。
     *
     * @param array<string, mixed> $overrides 递归覆盖
     * @return array<string, mixed>
     */
    public function transaction(array $overrides = []): array
    {
        return array_replace_recursive([
            'appid'            => self::APP_ID,
            'mchid'            => $this->mchId,
            'out_trade_no'     => 'R20260916120000' . random_int(10000000, 99999999),
            'transaction_id'   => '4200001234202609160000000001',
            'trade_type'       => 'NATIVE',
            'trade_state'      => 'SUCCESS',
            'trade_state_desc' => '支付成功',
            'bank_type'        => 'OTHERS',
            'success_time'     => '2026-09-16T12:01:02+08:00',
            'payer'            => ['openid' => 'oUpF8uMuAJO_M2pxb1Q9zNjWeS6o'],
            'amount'           => ['total' => 1234, 'payer_total' => 1234, 'currency' => 'CNY', 'payer_currency' => 'CNY'],
        ], $overrides);
    }

    /**
     * 一条真实形态的微信支付回调：resource 用 APIv3 key 加密，整个 body 用平台私钥签名。
     *
     * @param array<string, mixed> $transaction
     */
    public function notification(
        array $transaction,
        string $eventType = 'TRANSACTION.SUCCESS',
        bool $publicKeyMode = false,
        ?int $timestamp = null,
        ?string $serial = null,
        ?string $signingPrivatePem = null,
        ?string $apiV3Key = null,
    ): NotifyRequest {
        $resourceNonce = Formatter::nonce(12);
        $aad = 'transaction';
        $body = json_encode([
            'id'            => 'EV-2026091612' . bin2hex(random_bytes(6)),
            'create_time'   => '2026-09-16T12:01:03+08:00',
            'resource_type' => 'encrypt-resource',
            'event_type'    => $eventType,
            'summary'       => '支付成功',
            'resource'      => [
                'original_type'   => 'transaction',
                'algorithm'       => 'AEAD_AES_256_GCM',
                'ciphertext'      => AesGcm::encrypt(
                    json_encode($transaction, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    $apiV3Key ?? $this->apiV3Key,
                    $resourceNonce,
                    $aad,
                ),
                'associated_data' => $aad,
                'nonce'           => $resourceNonce,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $ts = (string) ($timestamp ?? time());
        $nonce = Formatter::nonce();

        return new NotifyRequest([
            'wechatpay-timestamp' => $ts,
            'wechatpay-nonce'     => $nonce,
            'wechatpay-signature' => Rsa::sign(Formatter::response($ts, $nonce, $body), $signingPrivatePem ?? $this->platformPrivatePem),
            'wechatpay-serial'    => $serial ?? ($publicKeyMode ? self::PUBLIC_KEY_ID : $this->platformSerial),
            'content-type'        => 'application/json',
        ], $body, []);
    }

    /** 保留签名头、替换 body（用于篡改测试） */
    public function withBody(NotifyRequest $request, string $rawBody): NotifyRequest
    {
        return new NotifyRequest($request->headers, $rawBody, $request->form);
    }

    public function cleanup(): void
    {
        PaymentKeys::removeDir($this->dir);
    }
}
