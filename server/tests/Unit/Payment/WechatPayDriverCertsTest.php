<?php

declare(strict_types=1);

namespace tests\Unit\Payment;

use core\payment\driver\WechatPayDriver;
use core\payment\dto\CreateOrderRequest;
use core\payment\dto\RefundRequest;
use core\payment\dto\RefundResult;
use core\payment\exception\GatewayException;
use core\payment\exception\GatewayResultUnknownException;
use core\payment\TradeType;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Response;
use tests\Support\Payment\PaymentKeys;
use tests\Support\Payment\WechatPayFixture;
use tests\TestCase;

final class WechatPayDriverCertsTest extends TestCase
{
    private const CODE_URL_BODY = '{"code_url":"weixin://wxpay/bizpayurl?pr=abc"}';

    private WechatPayFixture $fx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fx = new WechatPayFixture();
    }

    protected function tearDown(): void
    {
        // 失败路径测试会把缓存目录改成只读、把文件改成不可读：先恢复权限，cleanup 才删得掉
        if (is_dir($this->fx->certCacheDir())) {
            chmod($this->fx->certCacheDir(), 0o755);
            foreach (glob($this->fx->certCacheDir() . '/*') ?: [] as $dir) {
                chmod($dir, 0o755);
                foreach (glob($dir . '/*.pem') ?: [] as $file) {
                    chmod($file, 0o644);
                }
            }
        }
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

    // ---- 证书轮换

    public function test_rotated_platform_serial_triggers_one_download_and_next_call_succeeds(): void
    {
        $this->fx->seedCertCache();
        $rotated = $this->rotatedPlatform();
        $history = [];
        $driver = new WechatPayDriver($this->fx->config(), $this->fx->handler([
            $this->fx->signedResponse(200, self::CODE_URL_BODY, $rotated['serial'], $rotated['private']),
            $this->fx->certificatesResponse($rotated['cert'], $rotated['private'], $rotated['serial']),
            $this->fx->signedResponse(200, self::CODE_URL_BODY, $rotated['serial'], $rotated['private']),
        ], $history));

        try {
            $driver->create($this->nativeRequest());
            $this->fail('轮换后首个应答验签失败，本次仍应为结果不确定（不重试）');
        } catch (GatewayResultUnknownException) {
        }
        $this->assertCount(2, $history);
        $this->assertSame('/v3/certificates', $history[1]['request']->getUri()->getPath());
        $this->assertFileExists($this->merchantCertDir() . '/' . $rotated['serial'] . '.pem');

        $result = $driver->create($this->nativeRequest());

        $this->assertSame(['code_url' => 'weixin://wxpay/bizpayurl?pr=abc'], $result->data);
        $this->assertCount(3, $history);
        $this->assertSame('/v3/pay/transactions/native', $history[2]['request']->getUri()->getPath());
    }

    public function test_rotated_serial_within_throttle_window_does_not_download(): void
    {
        $this->fx->seedCertCache();
        touch($this->merchantCertDir() . '/.refreshed_at');
        $rotated = $this->rotatedPlatform();
        $history = [];
        $driver = new WechatPayDriver($this->fx->config(), $this->fx->handler([
            $this->fx->signedResponse(200, self::CODE_URL_BODY, $rotated['serial'], $rotated['private']),
            $this->fx->signedResponse(200, self::CODE_URL_BODY, $rotated['serial'], $rotated['private']),
        ], $history));

        foreach ([1, 2] as $attempt) {
            try {
                $driver->create($this->nativeRequest());
                $this->fail("限频窗口内第 {$attempt} 次调用应为结果不确定");
            } catch (GatewayResultUnknownException) {
            }
        }

        $this->assertCount(2, $history);
        foreach ($history as $entry) {
            $this->assertSame('/v3/pay/transactions/native', $entry['request']->getUri()->getPath());
        }
    }

    public function test_public_key_mode_does_not_download_on_unknown_serial(): void
    {
        $rotated = $this->rotatedPlatform();
        $history = [];
        $driver = new WechatPayDriver($this->fx->config(true), $this->fx->handler([
            $this->fx->signedResponse(200, self::CODE_URL_BODY, $rotated['serial'], $rotated['private']),
        ], $history));

        try {
            $driver->create($this->nativeRequest());
            $this->fail('公钥模式下未知序列号应为结果不确定');
        } catch (GatewayResultUnknownException) {
        }

        $this->assertCount(1, $history);
        $this->assertDirectoryDoesNotExist($this->fx->certCacheDir());
    }

    // ---- 缓存 I/O 失败（按 webman 的错误处理器把 Warning 转成 ErrorException）

    public function test_unwritable_cache_root_is_gateway_exception_without_sending(): void
    {
        mkdir($this->fx->certCacheDir(), 0o555, true);
        $history = [];
        $driver = new WechatPayDriver($this->fx->config(), $this->fx->handler([], $history));

        $thrown = $this->underWebmanErrorHandler(fn (): ?\Throwable => $this->catchThrowable(fn () => $driver->create($this->nativeRequest())));

        $this->assertInstanceOf(GatewayException::class, $thrown);
        $this->assertSame([], $history);
    }

    public function test_unwritable_cache_root_makes_refund_failed_without_sending(): void
    {
        mkdir($this->fx->certCacheDir(), 0o555, true);
        $history = [];
        $driver = new WechatPayDriver($this->fx->config(), $this->fx->handler([], $history));

        $result = $this->underWebmanErrorHandler(
            static fn (): RefundResult => $driver->refund(new RefundRequest('R2026091612000012345678', 'F2026091612300087654321', 500, 1234, '')),
        );

        $this->assertSame(RefundResult::FAILED, $result->status);
        $this->assertSame([], $history);
    }

    public function test_unwritable_merchant_dir_blocks_download_before_sending(): void
    {
        // 限频标记写不进去：不发下载请求，否则每次调用都会重下
        mkdir($this->merchantCertDir(), 0o755, true);
        chmod($this->merchantCertDir(), 0o555);
        $history = [];
        $driver = new WechatPayDriver($this->fx->config(), $this->fx->handler([$this->fx->certificatesResponse()], $history));

        $thrown = $this->underWebmanErrorHandler(fn (): ?\Throwable => $this->catchThrowable(fn () => $driver->create($this->nativeRequest())));

        $this->assertInstanceOf(GatewayException::class, $thrown);
        $this->assertSame([], $history);
    }

    public function test_cert_write_failure_after_download_is_gateway_exception(): void
    {
        // 标记文件已存在且过了限频窗口（能更新 mtime），但目录只读：下载成功、证书写不进缓存
        mkdir($this->merchantCertDir(), 0o755, true);
        touch($this->merchantCertDir() . '/.refreshed_at', time() - 61);
        chmod($this->merchantCertDir(), 0o555);
        $history = [];
        $driver = new WechatPayDriver($this->fx->config(), $this->fx->handler([$this->fx->certificatesResponse()], $history));

        $thrown = $this->underWebmanErrorHandler(fn (): ?\Throwable => $this->catchThrowable(fn () => $driver->create($this->nativeRequest())));

        $this->assertInstanceOf(GatewayException::class, $thrown);
        $this->assertCount(1, $history);
        $this->assertSame('/v3/certificates', $history[0]['request']->getUri()->getPath());
    }

    public function test_undeletable_expired_cert_and_unreadable_cert_are_skipped_silently(): void
    {
        // 过期证书删不掉（等同于另一个 worker 已先删掉）、某个缓存文件不可读：都跳过，不抛异常
        $this->fx->seedCertCache();
        $key = openssl_pkey_get_private($this->fx->platformPrivatePem);
        $csr = openssl_csr_new(['commonName' => 'expired-platform'], $key);
        $cert = openssl_csr_sign($csr, null, $key, 0, [], 0x6ABCDEF1);
        openssl_x509_export($cert, $expiredPem);
        $this->fx->seedCertCache($expiredPem, '6ABCDEF1');
        $this->fx->seedCertCache($this->fx->platformCertPem, '7ABCDEF1');
        chmod($this->merchantCertDir() . '/7ABCDEF1.pem', 0o000);
        chmod($this->merchantCertDir(), 0o555);

        $history = [];
        $driver = new WechatPayDriver($this->fx->config(), $this->fx->handler([$this->codeUrlResponse()], $history));

        $result = $this->underWebmanErrorHandler(fn () => $driver->create($this->nativeRequest()));

        $this->assertSame(['code_url' => 'weixin://wxpay/bizpayurl?pr=abc'], $result->data);
        $this->assertCount(1, $history);
        $this->assertFileExists($this->merchantCertDir() . '/6ABCDEF1.pem');
    }

    public function test_download_failure_chains_the_underlying_exception(): void
    {
        $history = [];
        $driver = new WechatPayDriver($this->fx->config(), $this->fx->handler([new Response(500, [], 'busy')], $history));

        try {
            $driver->create($this->nativeRequest());
            $this->fail('证书下载失败应抛 GatewayException');
        } catch (GatewayException $e) {
            $this->assertInstanceOf(ServerException::class, $e->getPrevious());
        }
    }

    /** @return array{private: string, cert: string, serial: string} 轮换后的新平台证书 */
    private function rotatedPlatform(): array
    {
        $pair = PaymentKeys::rsaPair();
        $cert = PaymentKeys::selfSignedCert($pair['private'], WechatPayFixture::randomSerial());

        return ['private' => $pair['private'], 'cert' => $cert['cert'], 'serial' => $cert['serial']];
    }

    private function catchThrowable(callable $fn): ?\Throwable
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            return $e;
        }

        return null;
    }

    /**
     * 模拟生产 worker：webman App 把 error_reporting 设为 E_ALL，support/bootstrap.php 的处理器把
     * 未被 @ 抑制的 Warning 抛成 ErrorException。测试进程的 error_reporting 不含 E_WARNING，需临时调高。
     *
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    private function underWebmanErrorHandler(callable $fn): mixed
    {
        $level = error_reporting(E_ALL);
        set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0): bool {
            if ((error_reporting() & $severity) !== 0) {
                throw new \ErrorException($message, 0, $severity, $file, $line);
            }

            return false;
        });

        try {
            return $fn();
        } finally {
            restore_error_handler();
            error_reporting($level);
        }
    }
}
