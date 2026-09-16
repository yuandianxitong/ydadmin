<?php

declare(strict_types=1);

namespace tests\Unit\Payment;

use core\payment\driver\WechatPayDriver;
use core\payment\dto\CreateOrderRequest;
use core\payment\exception\GatewayException;
use core\payment\exception\GatewayResultUnknownException;
use core\payment\TradeType;
use GuzzleHttp\Psr7\Response;
use tests\Support\Payment\PaymentKeys;
use tests\Support\Payment\WechatPayFixture;
use tests\TestCase;

final class WechatPayDriverCertsTest extends TestCase
{
    private WechatPayFixture $fx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fx = new WechatPayFixture();
    }

    protected function tearDown(): void
    {
        $this->fx->cleanup();
        parent::tearDown();
    }

    private function nativeRequest(): CreateOrderRequest
    {
        return new CreateOrderRequest(
            'R20260916120000' . random_int(10000000, 99999999),
            TradeType::NATIVE,
            '余额充值',
            100,
            new \DateTimeImmutable('+30 minutes'),
            'https://example.test/api/payment/notify/wechat',
        );
    }

    private function codeUrlResponse(bool $publicKeyMode = false): Response
    {
        return $this->fx->jsonResponse(200, ['code_url' => 'weixin://wxpay/bizpayurl?pr=abc'], $publicKeyMode);
    }

    private function merchantCertDir(?string $mchId = null): string
    {
        return $this->fx->certCacheDir() . '/' . ($mchId ?? $this->fx->mchId);
    }

    public function test_cert_mode_downloads_on_first_request_and_caches_under_merchant_dir(): void
    {
        $history = [];
        $driver = new WechatPayDriver($this->fx->config(), $this->fx->handler([
            $this->fx->certificatesResponse(),
            $this->codeUrlResponse(),
        ], $history));

        $driver->create($this->nativeRequest());

        $this->assertCount(2, $history);
        $this->assertSame('/v3/certificates', $history[0]['request']->getUri()->getPath());
        $this->assertSame('/v3/pay/transactions/native', $history[1]['request']->getUri()->getPath());
        $this->assertFileExists($this->merchantCertDir() . '/' . $this->fx->platformSerial . '.pem');
        $this->assertFileExists($this->merchantCertDir() . '/.refreshed_at');
    }

    public function test_cached_cert_is_used_without_download(): void
    {
        $this->fx->seedCertCache();
        $history = [];
        $driver = new WechatPayDriver($this->fx->config(), $this->fx->handler([$this->codeUrlResponse()], $history));

        $driver->create($this->nativeRequest());

        $this->assertCount(1, $history);
        $this->assertSame('/v3/pay/transactions/native', $history[0]['request']->getUri()->getPath());
    }

    public function test_cert_cache_is_isolated_per_merchant(): void
    {
        // 缓存里只有「另一个商户号」的证书：本商户必须自己下载，而不是拿别人的证书验签（SaaS 共用目录的缺陷）
        $this->fx->seedCertCache(mchId: '1900000001');
        $history = [];
        $driver = new WechatPayDriver($this->fx->config(), $this->fx->handler([
            $this->fx->certificatesResponse(),
            $this->codeUrlResponse(),
        ], $history));

        $driver->create($this->nativeRequest());

        $this->assertSame('/v3/certificates', $history[0]['request']->getUri()->getPath());
    }

    public function test_expired_cached_cert_is_removed_and_redownloaded(): void
    {
        $key = openssl_pkey_get_private($this->fx->platformPrivatePem);
        $csr = openssl_csr_new(['commonName' => 'expired-platform'], $key);
        $cert = openssl_csr_sign($csr, null, $key, 0, [], 0x6ABCDEF1);  // 有效期 0 天：validTo == 签发时刻
        openssl_x509_export($cert, $expiredPem);
        $this->fx->seedCertCache($expiredPem, '6ABCDEF1');

        $history = [];
        $driver = new WechatPayDriver($this->fx->config(), $this->fx->handler([
            $this->fx->certificatesResponse(),
            $this->codeUrlResponse(),
        ], $history));
        $driver->create($this->nativeRequest());

        $this->assertFileDoesNotExist($this->merchantCertDir() . '/6ABCDEF1.pem');
        $this->assertSame('/v3/certificates', $history[0]['request']->getUri()->getPath());
    }

    public function test_download_failure_is_gateway_exception_and_throttled(): void
    {
        $history = [];
        $driver = new WechatPayDriver($this->fx->config(), $this->fx->handler([new Response(500, [], 'busy')], $history));

        try {
            $driver->create($this->nativeRequest());
            $this->fail('证书下载失败时下单应抛 GatewayException（请求没有发出）');
        } catch (GatewayException) {
        }
        $this->assertCount(1, $history);
        $this->assertFileExists($this->merchantCertDir() . '/.refreshed_at');

        // 限频窗口内：新驱动实例不再下载，直接失败
        $second = [];
        $again = new WechatPayDriver($this->fx->config(), $this->fx->handler([], $second));
        try {
            $again->create($this->nativeRequest());
            $this->fail('限频窗口内应直接抛 GatewayException');
        } catch (GatewayException) {
        }
        $this->assertSame([], $second);
    }

    public function test_download_is_retried_after_refresh_interval(): void
    {
        mkdir($this->merchantCertDir(), 0o755, true);
        touch($this->merchantCertDir() . '/.refreshed_at', time() - 61);

        $history = [];
        $driver = new WechatPayDriver($this->fx->config(), $this->fx->handler([
            $this->fx->certificatesResponse(),
            $this->codeUrlResponse(),
        ], $history));
        $driver->create($this->nativeRequest());

        $this->assertCount(2, $history);
    }

    public function test_downloaded_cert_whose_real_serial_differs_from_claimed_is_discarded(): void
    {
        // 下发数据声称序列号 ABCDEF12，证书真实序列号却是 platformSerial：不写缓存、不信任
        $history = [];
        $driver = new WechatPayDriver($this->fx->config(), $this->fx->handler([
            $this->fx->certificatesResponse(claimedSerial: 'ABCDEF12'),
        ], $history));

        try {
            $driver->create($this->nativeRequest());
            $this->fail('序列号不符的证书应被丢弃，结果为证书不可用');
        } catch (GatewayException) {
        }
        $this->assertFileDoesNotExist($this->merchantCertDir() . '/ABCDEF12.pem');
    }

    public function test_public_key_mode_never_downloads_certificates(): void
    {
        $history = [];
        $driver = new WechatPayDriver($this->fx->config(true), $this->fx->handler([$this->codeUrlResponse(true)], $history));

        $driver->create($this->nativeRequest());

        $this->assertCount(1, $history);
        $this->assertSame('/v3/pay/transactions/native', $history[0]['request']->getUri()->getPath());
        $this->assertDirectoryDoesNotExist($this->fx->certCacheDir());
    }

    public function test_response_signed_with_unknown_serial_is_result_unknown(): void
    {
        $this->fx->seedCertCache();
        $other = PaymentKeys::rsaPair();
        $history = [];
        $driver = new WechatPayDriver($this->fx->config(), $this->fx->handler([
            $this->fx->signedResponse(200, '{"code_url":"weixin://x"}', 'DEADBEEF', $other['private']),
        ], $history));

        $this->expectException(GatewayResultUnknownException::class);
        $driver->create($this->nativeRequest());
    }
}
