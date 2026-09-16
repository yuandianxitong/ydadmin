<?php

declare(strict_types=1);

namespace tests\Unit\Payment;

use core\exception\BusinessException;
use core\payment\Channel;
use core\payment\config\AlipayConfig;
use core\payment\config\WechatPayConfig;
use core\payment\dto\CreateOrderRequest;
use core\payment\dto\CreateOrderResult;
use core\payment\dto\NotifyAck;
use core\payment\dto\NotifyRequest;
use core\payment\dto\NotifyResult;
use core\payment\dto\RefundRequest;
use core\payment\dto\RefundResult;
use core\payment\dto\TradeQueryResult;
use core\payment\exception\GatewayException;
use core\payment\exception\GatewayResultUnknownException;
use core\payment\exception\NotifyVerificationException;
use core\payment\exception\PaymentConfigException;
use core\payment\exception\PaymentException;
use core\payment\GatewayResolver;
use core\payment\PaymentGatewayInterface;
use core\payment\TradeType;
use tests\TestCase;

final class PaymentContractTest extends TestCase
{
    public function test_channel_and_trade_type_constants(): void
    {
        $this->assertSame('wechat', Channel::WECHAT);
        $this->assertSame('alipay', Channel::ALIPAY);
        $this->assertSame(['wechat', 'alipay'], Channel::ALL);
        $this->assertSame(
            ['native', 'h5', 'app', 'jsapi', 'page', 'wap'],
            [TradeType::NATIVE, TradeType::H5, TradeType::APP, TradeType::JSAPI, TradeType::PAGE, TradeType::WAP],
        );
    }

    public function test_create_order_request_holds_values_and_defaults(): void
    {
        $expires = new \DateTimeImmutable('2026-09-16 12:30:00');
        $request = new CreateOrderRequest('R1', TradeType::NATIVE, '余额充值', 1230, $expires, 'https://example.com/api/payment/notify/wechat');

        $this->assertSame('R1', $request->orderNo);
        $this->assertSame('native', $request->tradeType);
        $this->assertSame('余额充值', $request->subject);
        $this->assertSame(1230, $request->amountCents);
        $this->assertSame($expires, $request->expiresAt);
        $this->assertSame('https://example.com/api/payment/notify/wechat', $request->notifyUrl);
        $this->assertNull($request->openid);
        $this->assertNull($request->clientIp);

        $jsapi = new CreateOrderRequest('R2', TradeType::JSAPI, 's', 1, $expires, 'u', 'openid-x', '1.2.3.4');
        $this->assertSame('openid-x', $jsapi->openid);
        $this->assertSame('1.2.3.4', $jsapi->clientIp);
    }

    public function test_dtos_are_readonly(): void
    {
        $result = new CreateOrderResult(TradeType::NATIVE, ['code_url' => 'weixin://x']);
        $this->expectException(\Error::class);
        /** @phpstan-ignore-next-line 故意写只读属性 */
        $result->tradeType = 'h5';
    }

    public function test_result_dtos_hold_values_and_defaults(): void
    {
        $create = new CreateOrderResult(TradeType::H5, ['h5_url' => 'https://wx']);
        $this->assertSame(['h5_url' => 'https://wx'], $create->data);

        $this->assertSame(['paid', 'pending', 'closed', 'not_found'], [
            TradeQueryResult::PAID, TradeQueryResult::PENDING, TradeQueryResult::CLOSED, TradeQueryResult::NOT_FOUND,
        ]);
        $pending = new TradeQueryResult(TradeQueryResult::PENDING);
        $this->assertNull($pending->tradeNo);
        $this->assertNull($pending->paidCents);
        $this->assertSame([], $pending->raw);
        $paid = new TradeQueryResult(TradeQueryResult::PAID, 'T1', 100, ['trade_state' => 'SUCCESS']);
        $this->assertSame(['T1', 100, ['trade_state' => 'SUCCESS']], [$paid->tradeNo, $paid->paidCents, $paid->raw]);

        $refundRequest = new RefundRequest('R1', 'F1', 50, 100, '用户申请');
        $this->assertSame(['R1', 'F1', 50, 100, '用户申请'], [
            $refundRequest->orderNo, $refundRequest->refundNo, $refundRequest->refundCents, $refundRequest->totalCents, $refundRequest->reason,
        ]);

        $this->assertSame(['success', 'processing', 'failed', 'not_found'], [
            RefundResult::SUCCESS, RefundResult::PROCESSING, RefundResult::FAILED, RefundResult::NOT_FOUND,
        ]);
        $refund = new RefundResult(RefundResult::PROCESSING);
        $this->assertNull($refund->channelRefundNo);
        $this->assertNull($refund->errorMsg);

        $notifyRequest = new NotifyRequest(['wechatpay-serial' => 'ABC'], '{"id":"1"}', ['a' => '1']);
        $this->assertSame(['wechatpay-serial' => 'ABC'], $notifyRequest->headers);
        $this->assertSame('{"id":"1"}', $notifyRequest->rawBody);
        $this->assertSame(['a' => '1'], $notifyRequest->form);

        $ignored = new NotifyResult(false);
        $this->assertSame([false, '', null, null, []], [$ignored->paid, $ignored->orderNo, $ignored->tradeNo, $ignored->paidCents, $ignored->raw]);
        $notified = new NotifyResult(true, 'R1', 'T1', 100, ['x' => 1]);
        $this->assertSame([true, 'R1', 'T1', 100, ['x' => 1]], [$notified->paid, $notified->orderNo, $notified->tradeNo, $notified->paidCents, $notified->raw]);

        $ack = new NotifyAck(200, 'text/plain', 'success');
        $this->assertSame([200, 'text/plain', 'success'], [$ack->status, $ack->contentType, $ack->body]);
    }

    public function test_exceptions_share_payment_base_and_are_not_business_exceptions(): void
    {
        foreach ([
            GatewayException::class,
            GatewayResultUnknownException::class,
            NotifyVerificationException::class,
            PaymentConfigException::class,
        ] as $class) {
            $exception = new $class('内部细节');
            $this->assertInstanceOf(PaymentException::class, $exception, $class);
            $this->assertInstanceOf(\RuntimeException::class, $exception, $class);
            $this->assertNotInstanceOf(BusinessException::class, $exception, "{$class} 不得继承 BusinessException，否则内部细节会被当业务文案返回客户端");
            $this->assertTrue((new \ReflectionClass($class))->isFinal(), "{$class} 应为 final");
        }
        $this->assertFalse((new \ReflectionClass(PaymentException::class))->isFinal());
    }

    public function test_gateway_interfaces_declare_the_agreed_methods(): void
    {
        $methods = static fn (string $interface): array => array_map(
            static fn (\ReflectionMethod $m): string => $m->getName(),
            (new \ReflectionClass($interface))->getMethods(),
        );

        $this->assertSame(
            ['create', 'query', 'close', 'refund', 'queryRefund', 'verifyNotify', 'notifyAck'],
            $methods(PaymentGatewayInterface::class),
        );
        $this->assertSame(['isEnabled', 'gateway'], $methods(GatewayResolver::class));
    }

    public function test_alipay_config_gateway_url_follows_sandbox_flag(): void
    {
        $production = new AlipayConfig('2021000000000000', 'PRIVATE', 'PUBLIC', false, 5.0, 10.0);
        $sandbox = new AlipayConfig('9021000000000000', 'PRIVATE', 'PUBLIC', true, 5.0, 10.0);

        $this->assertSame('https://openapi.alipay.com/gateway.do', $production->gatewayUrl());
        $this->assertSame('https://openapi-sandbox.dl.alipaydev.com/gateway.do', $sandbox->gatewayUrl());
        $this->assertSame(AlipayConfig::GATEWAY, $production->gatewayUrl());
        $this->assertSame(AlipayConfig::SANDBOX_GATEWAY, $sandbox->gatewayUrl());
        $this->assertSame(['2021000000000000', 'PRIVATE', 'PUBLIC', false, 5.0, 10.0], [
            $production->appId, $production->privateKey, $production->alipayPublicKey,
            $production->sandbox, $production->connectTimeout, $production->timeout,
        ]);
    }

    public function test_wechat_config_uses_public_key_only_when_both_id_and_key_present(): void
    {
        $make = static fn (?string $id, ?string $key): WechatPayConfig => new WechatPayConfig(
            'wxappid',
            '1900000001',
            str_repeat('k', 32),
            'MERCHANTSERIAL',
            'cert/apiclient_key.pem',
            $id,
            $key,
            '/tmp/wechatpay',
            60,
            5.0,
            10.0,
        );

        $this->assertTrue($make('PUB_KEY_ID_0114', "-----BEGIN PUBLIC KEY-----\nX\n-----END PUBLIC KEY-----")->usesPublicKey());
        $this->assertFalse($make(null, null)->usesPublicKey());
        $this->assertFalse($make('', '')->usesPublicKey());
        $this->assertFalse($make('PUB_KEY_ID_0114', null)->usesPublicKey());
        $this->assertFalse($make(null, 'KEY')->usesPublicKey());
        $this->assertFalse($make('PUB_KEY_ID_0114', '')->usesPublicKey());

        $config = $make(null, null);
        $this->assertSame(
            ['wxappid', '1900000001', str_repeat('k', 32), 'MERCHANTSERIAL', 'cert/apiclient_key.pem', '/tmp/wechatpay', 60, 5.0, 10.0],
            [$config->appId, $config->mchId, $config->apiV3Key, $config->merchantSerialNo, $config->privateKeyPath,
                $config->certCacheDir, $config->certRefreshInterval, $config->connectTimeout, $config->timeout],
        );
    }

    public function test_payment_config_file_has_expected_keys_and_types(): void
    {
        $this->assertSame(30, config('payment.order_expire_minutes'));
        $this->assertSame(10, config('payment.query_throttle_seconds'));
        $this->assertSame(10, config('payment.recharge_per_minute'));
        $this->assertSame(5.0, config('payment.connect_timeout'));
        $this->assertSame(10.0, config('payment.timeout'));
        $this->assertSame(runtime_path('cert/wechatpay'), config('payment.wechat_cert_dir'));
        $this->assertSame(60, config('payment.wechat_cert_refresh_interval'));
        $this->assertSame(100, config('payment.close_batch'));
        $this->assertSame(60, config('payment.close_grace_seconds'));
        $this->assertSame(100, config('payment.reconcile_batch'));
        $this->assertSame(120, config('payment.reconcile_min_age_seconds'));
        $this->assertSame(1800, config('payment.refund_not_found_fail_seconds'));
        $this->assertSame(86400, config('payment.refund_stuck_alert_seconds'));
        $this->assertGreaterThan(0.0, config('payment.connect_timeout'), '常驻 worker 下超时 0 = 无限等待');
        $this->assertGreaterThan(0.0, config('payment.timeout'), '常驻 worker 下超时 0 = 无限等待');
    }
}
