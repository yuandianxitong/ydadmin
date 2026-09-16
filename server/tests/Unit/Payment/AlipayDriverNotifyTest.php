<?php

declare(strict_types=1);

namespace tests\Unit\Payment;

use core\payment\driver\AlipayDriver;
use core\payment\dto\NotifyRequest;
use core\payment\exception\NotifyVerificationException;
use tests\Support\Payment\AlipayStub;
use tests\Support\Payment\PaymentKeys;
use tests\TestCase;

/** 支付宝异步通知：RSA2 验签、app_id 核对、只处理 TRADE_SUCCESS / TRADE_FINISHED（spec §5.3）。 */
final class AlipayDriverNotifyTest extends TestCase
{
    /** @var array{private: string, public: string} */
    private static array $app;
    /** @var array{private: string, public: string} */
    private static array $alipay;
    /** @var array{private: string, public: string} */
    private static array $stranger;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$app = PaymentKeys::rsaPair();
        self::$alipay = PaymentKeys::rsaPair();
        self::$stranger = PaymentKeys::rsaPair();
    }

    private function driver(): AlipayDriver
    {
        return new AlipayDriver(AlipayStub::config(self::$app['private'], self::$alipay['public']));
    }

    /**
     * @param array<string, string> $overrides
     * @return array<string, string>
     */
    private function params(array $overrides = []): array
    {
        return array_merge([
            'gmt_create'   => '2026-09-16 12:00:01',
            'charset'      => 'utf-8',
            'notify_type'  => 'trade_status_sync',
            'notify_id'    => '2026091600222120001000000000000000',
            'notify_time'  => '2026-09-16 12:00:05',
            'app_id'       => '2021000000000001',
            'out_trade_no' => 'R20260916120000123456',
            'trade_no'     => '2026091622001400000000000001',
            'trade_status' => 'TRADE_SUCCESS',
            'total_amount' => '12.30',
            'subject'      => '余额充值',
            'body'         => '',
            'version'      => '1.0',
        ], $overrides);
    }

    /** @param array<string, mixed> $form */
    private function notify(array $form): NotifyRequest
    {
        return new NotifyRequest(['content-type' => 'application/x-www-form-urlencoded'], http_build_query($form), $form);
    }

    public function test_valid_success_notify_is_paid(): void
    {
        $result = $this->driver()->verifyNotify($this->notify(AlipayStub::signNotify($this->params(), self::$alipay['private'])));

        $this->assertTrue($result->paid);
        $this->assertSame('R20260916120000123456', $result->orderNo);
        $this->assertSame('2026091622001400000000000001', $result->tradeNo);
        $this->assertSame(1230, $result->paidCents);
        $this->assertArrayNotHasKey('sign', $result->raw);
        $this->assertArrayNotHasKey('sign_type', $result->raw);
        $this->assertSame('TRADE_SUCCESS', $result->raw['trade_status']);
    }

    public function test_trade_finished_is_paid(): void
    {
        $result = $this->driver()->verifyNotify($this->notify(AlipayStub::signNotify($this->params(['trade_status' => 'TRADE_FINISHED']), self::$alipay['private'])));

        $this->assertTrue($result->paid);
    }

    public function test_verified_non_success_status_is_acknowledged_but_not_paid(): void
    {
        $result = $this->driver()->verifyNotify($this->notify(AlipayStub::signNotify($this->params(['trade_status' => 'WAIT_BUYER_PAY']), self::$alipay['private'])));

        $this->assertFalse($result->paid);
        $this->assertSame('R20260916120000123456', $result->orderNo);
    }

    public function test_empty_values_take_part_in_signature(): void
    {
        $signed = AlipayStub::signNotify($this->params(), self::$alipay['private']);
        unset($signed['body']);

        $this->expectException(NotifyVerificationException::class);

        $this->driver()->verifyNotify($this->notify($signed));
    }

    /** @return iterable<string, array{\Closure(array<string, string>, string): array<string, mixed>}> */
    public static function forgeries(): iterable
    {
        yield 'tampered amount' => [static function (array $params, string $alipayKey): array {
            $signed = AlipayStub::signNotify($params, $alipayKey);
            $signed['total_amount'] = '0.01';

            return $signed;
        }];
        yield 'missing sign' => [static function (array $params): array {
            return $params;
        }];
        yield 'sign not base64' => [static function (array $params): array {
            return $params + ['sign' => '***', 'sign_type' => 'RSA2'];
        }];
        yield 'sign_type RSA' => [static function (array $params, string $alipayKey): array {
            $signed = AlipayStub::signNotify($params, $alipayKey);
            $signed['sign_type'] = 'RSA';

            return $signed;
        }];
        yield 'array value' => [static function (array $params, string $alipayKey): array {
            $signed = AlipayStub::signNotify($params, $alipayKey);
            $signed['subject'] = ['x'];

            return $signed;
        }];
    }

    /** @param \Closure(array<string, string>, string): array<string, mixed> $forge */
    #[\PHPUnit\Framework\Attributes\DataProvider('forgeries')]
    public function test_forged_notify_is_rejected(\Closure $forge): void
    {
        $this->expectException(NotifyVerificationException::class);

        $this->driver()->verifyNotify($this->notify($forge($this->params(), self::$alipay['private'])));
    }

    public function test_notify_signed_by_another_key_is_rejected(): void
    {
        $this->expectException(NotifyVerificationException::class);

        $this->driver()->verifyNotify($this->notify(AlipayStub::signNotify($this->params(), self::$stranger['private'])));
    }

    public function test_correctly_signed_notify_for_another_app_is_rejected(): void
    {
        $this->expectException(NotifyVerificationException::class);

        $this->driver()->verifyNotify($this->notify(AlipayStub::signNotify($this->params(['app_id' => '2021009999999999']), self::$alipay['private'])));
    }

    public function test_success_notify_with_invalid_amount_is_rejected(): void
    {
        $this->expectException(NotifyVerificationException::class);

        $this->driver()->verifyNotify($this->notify(AlipayStub::signNotify($this->params(['total_amount' => '12.345']), self::$alipay['private'])));
    }

    public function test_ack_bodies(): void
    {
        $success = $this->driver()->notifyAck(true);
        $failure = $this->driver()->notifyAck(false);

        $this->assertSame([200, 'text/plain', 'success'], [$success->status, $success->contentType, $success->body]);
        $this->assertSame([200, 'text/plain', 'fail'], [$failure->status, $failure->contentType, $failure->body]);
    }
}
