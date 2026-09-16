<?php

declare(strict_types=1);

namespace tests\Unit\Payment;

use core\payment\driver\WechatPayDriver;
use core\payment\dto\NotifyRequest;
use core\payment\exception\NotifyVerificationException;
use core\payment\PaymentGatewayInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use tests\Support\Payment\PaymentKeys;
use tests\Support\Payment\WechatPayFixture;
use tests\TestCase;

final class WechatPayDriverNotifyTest extends TestCase
{
    private WechatPayFixture $fx;

    /** @var array<int, array<string, mixed>> */
    private array $history = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->fx = new WechatPayFixture();
        $this->history = [];
    }

    protected function tearDown(): void
    {
        // I/O 失败测试会把商户证书目录改成只读：先恢复权限，cleanup 才删得掉
        if (is_dir($this->merchantCertDir())) {
            chmod($this->merchantCertDir(), 0o755);
        }
        $this->fx->cleanup();
        parent::tearDown();
    }

    /** @param list<\Psr\Http\Message\ResponseInterface|\Throwable> $queue */
    private function driver(bool $publicKeyMode = false, array $queue = []): WechatPayDriver
    {
        return new WechatPayDriver($this->fx->config($publicKeyMode), $this->fx->handler($queue, $this->history));
    }

    private function merchantCertDir(): string
    {
        return $this->fx->certCacheDir() . '/' . $this->fx->mchId;
    }

    private function assertRejected(WechatPayDriver $driver, NotifyRequest $request): void
    {
        try {
            $driver->verifyNotify($request);
            $this->fail('回调应被拒绝');
        } catch (NotifyVerificationException $e) {
            $this->assertNotSame('', $e->getMessage());
        }
    }

    public function test_driver_implements_gateway_interface(): void
    {
        $this->assertInstanceOf(PaymentGatewayInterface::class, $this->driver(true));
    }

    public function test_valid_cert_mode_notification_is_paid_without_network(): void
    {
        $this->fx->seedCertCache();
        $transaction = $this->fx->transaction(['out_trade_no' => 'R2026091612000012345678']);

        $result = $this->driver()->verifyNotify($this->fx->notification($transaction));

        $this->assertTrue($result->paid);
        $this->assertSame('R2026091612000012345678', $result->orderNo);
        $this->assertSame('4200001234202609160000000001', $result->tradeNo);
        $this->assertSame(1234, $result->paidCents);
        $this->assertSame($transaction, $result->raw);
        $this->assertSame([], $this->history);
    }

    public function test_valid_public_key_mode_notification_is_paid(): void
    {
        $result = $this->driver(true)->verifyNotify($this->fx->notification($this->fx->transaction(), publicKeyMode: true));

        $this->assertTrue($result->paid);
        $this->assertSame([], $this->history);
    }

    public function test_tampered_body_is_rejected(): void
    {
        $this->fx->seedCertCache();
        $request = $this->fx->notification($this->fx->transaction());
        $tampered = $this->fx->withBody($request, str_replace('TRANSACTION.SUCCESS', 'TRANSACTION.SUCCESS ', $request->rawBody));

        $this->assertRejected($this->driver(), $tampered);
    }

    public function test_signature_from_other_key_is_rejected(): void
    {
        $this->fx->seedCertCache();
        $other = PaymentKeys::rsaPair();

        $this->assertRejected($this->driver(), $this->fx->notification($this->fx->transaction(), signingPrivatePem: $other['private']));
    }

    public function test_garbage_signature_is_rejected(): void
    {
        $this->fx->seedCertCache();
        $request = $this->fx->notification($this->fx->transaction());
        $headers = ['wechatpay-signature' => '!!!not-base64!!!'] + $request->headers;

        $this->assertRejected($this->driver(), new NotifyRequest($headers, $request->rawBody, []));
    }

    /** @return array<string, array{int}> */
    public static function staleOffsets(): array
    {
        return ['301 秒前' => [-301], '301 秒后' => [301]];
    }

    #[DataProvider('staleOffsets')]
    public function test_timestamp_outside_window_is_rejected(int $offset): void
    {
        $this->fx->seedCertCache();

        $this->assertRejected($this->driver(), $this->fx->notification($this->fx->transaction(), timestamp: time() + $offset));
    }

    public function test_non_numeric_timestamp_is_rejected(): void
    {
        $this->fx->seedCertCache();
        $request = $this->fx->notification($this->fx->transaction());
        $headers = ['wechatpay-timestamp' => '1e9'] + $request->headers;

        $this->assertRejected($this->driver(), new NotifyRequest($headers, $request->rawBody, []));
    }

    /** @return array<string, array{string}> */
    public static function requiredHeaders(): array
    {
        return [
            'timestamp' => ['wechatpay-timestamp'],
            'nonce'     => ['wechatpay-nonce'],
            'signature' => ['wechatpay-signature'],
            'serial'    => ['wechatpay-serial'],
        ];
    }

    #[DataProvider('requiredHeaders')]
    public function test_missing_header_is_rejected(string $header): void
    {
        $this->fx->seedCertCache();
        $request = $this->fx->notification($this->fx->transaction());
        $headers = $request->headers;
        unset($headers[$header]);

        $this->assertRejected($this->driver(), new NotifyRequest($headers, $request->rawBody, []));
    }

    public function test_public_key_mode_rejects_other_serial_without_network(): void
    {
        $this->assertRejected(
            $this->driver(true),
            $this->fx->notification($this->fx->transaction(), publicKeyMode: true, serial: 'PUB_KEY_ID_9999'),
        );
        $this->assertSame([], $this->history);
    }

    public function test_cert_mode_unknown_serial_downloads_once_and_verifies(): void
    {
        // 缓存里只有旧证书；回调用轮换后的新证书签名
        $this->fx->seedCertCache();
        $rotated = PaymentKeys::rsaPair();
        $rotatedSerial = WechatPayFixture::randomSerial();
        $rotatedCert = PaymentKeys::selfSignedCert($rotated['private'], $rotatedSerial);

        $driver = $this->driver(queue: [$this->fx->certificatesResponse($rotatedCert['cert'], $rotated['private'], $rotatedCert['serial'])]);
        $result = $driver->verifyNotify($this->fx->notification(
            $this->fx->transaction(),
            serial: $rotatedCert['serial'],
            signingPrivatePem: $rotated['private'],
        ));

        $this->assertTrue($result->paid);
        $this->assertCount(1, $this->history);
        $this->assertSame('/v3/certificates', $this->history[0]['request']->getUri()->getPath());
        $this->assertFileExists($this->merchantCertDir() . '/' . $rotatedCert['serial'] . '.pem');
    }

    public function test_cert_mode_unknown_serial_is_rejected_without_download_inside_refresh_window(): void
    {
        $this->fx->seedCertCache();
        touch($this->merchantCertDir() . '/.refreshed_at');
        $other = PaymentKeys::rsaPair();

        $this->assertRejected($this->driver(), $this->fx->notification(
            $this->fx->transaction(),
            serial: 'ABCDEF12',
            signingPrivatePem: $other['private'],
        ));
        $this->assertSame([], $this->history);
    }

    public function test_cert_mode_serial_still_unknown_after_download_is_rejected(): void
    {
        $this->fx->seedCertCache();
        $other = PaymentKeys::rsaPair();

        // 下载到的仍是旧证书：伪造的序列号查不到
        $this->assertRejected(
            $this->driver(queue: [$this->fx->certificatesResponse()]),
            $this->fx->notification($this->fx->transaction(), serial: 'ABCDEF12', signingPrivatePem: $other['private']),
        );
        $this->assertCount(1, $this->history);
        $this->assertFileExists($this->merchantCertDir() . '/.refreshed_at');
    }

    public function test_mchid_mismatch_is_rejected(): void
    {
        $this->assertRejected(
            $this->driver(true),
            $this->fx->notification($this->fx->transaction(['mchid' => '1900000001']), publicKeyMode: true),
        );
    }

    public function test_appid_mismatch_is_rejected(): void
    {
        $this->assertRejected(
            $this->driver(true),
            $this->fx->notification($this->fx->transaction(['appid' => 'wx0000000000000000']), publicKeyMode: true),
        );
    }

    public function test_resource_encrypted_with_other_key_is_rejected(): void
    {
        $this->assertRejected(
            $this->driver(true),
            $this->fx->notification($this->fx->transaction(), publicKeyMode: true, apiV3Key: str_repeat('z', 32)),
        );
    }

    public function test_non_transaction_success_event_is_not_paid(): void
    {
        // 退款事件的资源里没有 appid：验签、解密、mchid 核对都通过后应答成功、不处理（异议 5）
        $refund = ['mchid' => $this->fx->mchId, 'out_trade_no' => 'R2026091612000012345678', 'refund_status' => 'SUCCESS'];

        $result = $this->driver(true)->verifyNotify($this->fx->notification($refund, 'REFUND.SUCCESS', true));

        $this->assertFalse($result->paid);
    }

    public function test_trade_state_other_than_success_is_not_paid(): void
    {
        $result = $this->driver(true)->verifyNotify(
            $this->fx->notification($this->fx->transaction(['trade_state' => 'NOTPAY']), publicKeyMode: true),
        );

        $this->assertFalse($result->paid);
    }

    public function test_paid_notification_missing_amount_is_rejected(): void
    {
        $transaction = $this->fx->transaction();
        unset($transaction['amount']);

        $this->assertRejected($this->driver(true), $this->fx->notification($transaction, publicKeyMode: true));
    }

    public function test_form_params_cannot_override_signed_resource(): void
    {
        $request = $this->fx->notification($this->fx->transaction(['out_trade_no' => 'R2026091612000011111111']), publicKeyMode: true);
        // 伪造的 GET/表单参数：同样合法加密的另一笔交易，也不能替换已验签 body 里的数据
        $forged = $this->fx->notification($this->fx->transaction(['out_trade_no' => 'R2026091612000099999999', 'amount' => ['total' => 1]]), publicKeyMode: true);
        $envelope = json_decode($forged->rawBody, true, 512, JSON_THROW_ON_ERROR);
        $withForm = new NotifyRequest($request->headers, $request->rawBody, ['resource' => $envelope['resource'], 'out_trade_no' => 'R2026091612000099999999']);

        $result = $this->driver(true)->verifyNotify($withForm);

        $this->assertSame('R2026091612000011111111', $result->orderNo);
        $this->assertSame(1234, $result->paidCents);
    }

    public function test_body_that_is_not_json_is_rejected_even_when_signed(): void
    {
        $request = $this->fx->notification($this->fx->transaction(), publicKeyMode: true);
        $ts = (string) time();
        $nonce = 'n0nce';
        $body = 'not-json';
        $headers = [
            'wechatpay-signature' => \WeChatPay\Crypto\Rsa::sign(\WeChatPay\Formatter::response($ts, $nonce, $body), $this->fx->platformPrivatePem),
            'wechatpay-timestamp' => $ts,
            'wechatpay-nonce'     => $nonce,
        ] + $request->headers;

        $this->assertRejected($this->driver(true), new NotifyRequest($headers, $body, []));
    }

    public function test_notify_ack_shapes(): void
    {
        $driver = $this->driver(true);

        $ok = $driver->notifyAck(true);
        $this->assertSame([200, 'application/json', '{"code":"SUCCESS","message":"成功"}'], [$ok->status, $ok->contentType, $ok->body]);

        $fail = $driver->notifyAck(false);
        $this->assertSame([500, 'application/json', '{"code":"FAIL","message":"失败"}'], [$fail->status, $fail->contentType, $fail->body]);
    }

    public function test_unwritable_cert_dir_rejects_unknown_serial_without_download_under_webman_error_handler(): void
    {
        // 限频标记写不进去：downloadCerts() 不发请求并抛 GatewayException，回调侧仍以 NotifyVerificationException 拒绝
        $this->fx->seedCertCache();
        chmod($this->merchantCertDir(), 0o555);
        $other = PaymentKeys::rsaPair();
        $driver = $this->driver(queue: [$this->fx->certificatesResponse()]);
        $request = $this->fx->notification($this->fx->transaction(), serial: 'ABCDEF12', signingPrivatePem: $other['private']);

        $this->underWebmanErrorHandler(fn () => $this->assertRejected($driver, $request));
        $this->assertSame([], $this->history);
    }

    public function test_malformed_signature_and_ciphertext_are_rejected_under_webman_error_handler(): void
    {
        $driver = $this->driver(true);
        $request = $this->fx->notification($this->fx->transaction(), publicKeyMode: true);
        $garbageSignature = new NotifyRequest(['wechatpay-signature' => '!!!not-base64!!!'] + $request->headers, $request->rawBody, []);

        $ts = (string) time();
        $nonce = 'n0nce';
        $body = json_encode(['event_type' => 'TRANSACTION.SUCCESS', 'resource' => [
            'algorithm'       => 'AEAD_AES_256_GCM',
            'ciphertext'      => '%%%',
            'nonce'           => 'abc',
            'associated_data' => 'transaction',
        ]], JSON_THROW_ON_ERROR);
        $badCiphertext = new NotifyRequest([
            'wechatpay-signature' => \WeChatPay\Crypto\Rsa::sign(\WeChatPay\Formatter::response($ts, $nonce, $body), $this->fx->platformPrivatePem),
            'wechatpay-timestamp' => $ts,
            'wechatpay-nonce'     => $nonce,
        ] + $request->headers, $body, []);

        $this->underWebmanErrorHandler(function () use ($driver, $garbageSignature, $badCiphertext): void {
            $this->assertRejected($driver, $garbageSignature);
            $this->assertRejected($driver, $badCiphertext);
        });
    }

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
