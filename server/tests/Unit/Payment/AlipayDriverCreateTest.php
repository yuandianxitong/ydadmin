<?php

declare(strict_types=1);

namespace tests\Unit\Payment;

use core\payment\driver\AlipayDriver;
use core\payment\dto\CreateOrderRequest;
use core\payment\exception\GatewayException;
use core\payment\exception\PaymentConfigException;
use core\payment\TradeType;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use tests\Support\Payment\AlipayStub;
use tests\Support\Payment\PaymentKeys;
use tests\TestCase;

/**
 * 支付宝 page / wap / app 三种下单都是本地签名生成，不发网络请求（spec §5.1 第 7 步）。
 * 这里断言：参数齐全、签名能被应用公钥验过、表单值被转义、过期时间换算到北京时间、沙箱网关切换。
 */
final class AlipayDriverCreateTest extends TestCase
{
    /** @var array{private: string, public: string} */
    private static array $app;
    /** @var array{private: string, public: string} */
    private static array $alipay;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$app = PaymentKeys::rsaPair();
        self::$alipay = PaymentKeys::rsaPair();
    }

    /** @param list<array<string, mixed>> $history */
    private function driver(bool $sandbox = false, array &$history = []): AlipayDriver
    {
        $stack = HandlerStack::create(new MockHandler([]));
        $stack->push(Middleware::history($history));

        return new AlipayDriver(AlipayStub::config(self::$app['private'], self::$alipay['public'], $sandbox), $stack);
    }

    private function request(string $tradeType, string $subject = '余额充值'): CreateOrderRequest
    {
        return new CreateOrderRequest(
            'R20260916120000123456',
            $tradeType,
            $subject,
            1230,
            new \DateTimeImmutable('2026-09-16 12:30:00', new \DateTimeZone('UTC')),
            'https://example.com/api/payment/notify/alipay',
        );
    }

    /** @return array{action: string, params: array<string, string>} */
    private function parseForm(string $html): array
    {
        $this->assertSame(1, preg_match('/<form [^>]*action="([^"]+)" method="POST">/', $html, $form), '必须是 POST 表单');
        preg_match_all('/<input type="hidden" name="([^"]+)" value="([^"]*)"\/>/', $html, $matches, PREG_SET_ORDER);
        $params = [];
        foreach ($matches as $match) {
            $params[html_entity_decode($match[1], ENT_QUOTES, 'UTF-8')] = html_entity_decode($match[2], ENT_QUOTES, 'UTF-8');
        }

        return ['action' => html_entity_decode($form[1], ENT_QUOTES, 'UTF-8'), 'params' => $params];
    }

    /** @param array<string, string> $params */
    private function assertSignedByApp(array $params): void
    {
        $sign = $params['sign'];
        unset($params['sign']);
        ksort($params);
        $pairs = [];
        foreach ($params as $key => $value) {
            // 与 alipaysdk/easysdk 2.2.3 EasySDKKernel::getSignContent() 一致：trim 后为空的值不参与签名
            if (trim($value) !== '') {
                $pairs[] = $key . '=' . $value;
            }
        }
        $this->assertSame(
            1,
            openssl_verify(implode('&', $pairs), (string) base64_decode($sign, true), self::$app['public'], OPENSSL_ALGO_SHA256),
            '请求签名必须能被应用公钥验过',
        );
    }

    public function test_page_pay_builds_signed_auto_submit_form_without_network(): void
    {
        $history = [];
        $result = $this->driver(false, $history)->create($this->request(TradeType::PAGE));

        $this->assertSame(TradeType::PAGE, $result->tradeType);
        $this->assertSame(['body'], array_keys($result->data));
        $this->assertStringContainsString('<script>document.forms[0].submit();</script>', $result->data['body']);

        $form = $this->parseForm($result->data['body']);
        $this->assertSame('https://openapi.alipay.com/gateway.do?charset=utf-8', $form['action']);
        $params = $form['params'];
        $this->assertSame('2021000000000001', $params['app_id']);
        $this->assertSame('alipay.trade.page.pay', $params['method']);
        $this->assertSame('JSON', $params['format']);
        $this->assertSame('utf-8', $params['charset']);
        $this->assertSame('RSA2', $params['sign_type']);
        $this->assertSame('1.0', $params['version']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $params['timestamp']);
        $this->assertSame('https://example.com/api/payment/notify/alipay', $params['notify_url']);

        $biz = json_decode($params['biz_content'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame([
            'out_trade_no' => 'R20260916120000123456',
            'total_amount' => '12.30',
            'subject'      => '余额充值',
            'product_code' => 'FAST_INSTANT_TRADE_PAY',
            'time_expire'  => '2026-09-16 20:30:00',
        ], $biz, 'time_expire 必须换算到北京时间');
        $this->assertArrayNotHasKey('return_url', $params);
        $this->assertSignedByApp($params);
        $this->assertSame([], $history, '支付宝下单是本地生成，不得发请求');
    }

    public function test_blank_values_are_left_out_of_request_signature_like_the_official_sdk(): void
    {
        $request = new CreateOrderRequest(
            'R20260916120000123456',
            TradeType::PAGE,
            '余额充值',
            1230,
            new \DateTimeImmutable('2026-09-16 12:30:00', new \DateTimeZone('UTC')),
            '   ',
        );

        $params = $this->parseForm($this->driver()->create($request)->data['body'])['params'];

        $this->assertSame('   ', $params['notify_url']);
        $this->assertSignedByApp($params);
    }

    public function test_wap_pay_uses_quick_wap_way_and_sandbox_gateway(): void
    {
        $result = $this->driver(true)->create($this->request(TradeType::WAP));

        $form = $this->parseForm($result->data['body']);
        $this->assertSame('https://openapi-sandbox.dl.alipaydev.com/gateway.do?charset=utf-8', $form['action']);
        $this->assertSame('alipay.trade.wap.pay', $form['params']['method']);
        $biz = json_decode($form['params']['biz_content'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('QUICK_WAP_WAY', $biz['product_code']);
        $this->assertArrayNotHasKey('quit_url', $biz);
        $this->assertSignedByApp($form['params']);
    }

    public function test_app_pay_returns_url_encoded_order_string(): void
    {
        $history = [];
        $result = $this->driver(false, $history)->create($this->request(TradeType::APP));

        $this->assertSame(TradeType::APP, $result->tradeType);
        $this->assertStringNotContainsString('<form', $result->data['body']);
        parse_str($result->data['body'], $params);
        /** @var array<string, string> $params */
        $this->assertSame('alipay.trade.app.pay', $params['method']);
        $biz = json_decode($params['biz_content'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('QUICK_MSECURITY_PAY', $biz['product_code']);
        $this->assertSignedByApp($params);
        $this->assertSame([], $history);
    }

    public function test_form_values_are_html_escaped(): void
    {
        $subject = '"><script>alert(1)</script>&\'';
        $result = $this->driver()->create($this->request(TradeType::PAGE, $subject));

        $this->assertStringNotContainsString('<script>alert(1)</script>', $result->data['body']);
        $params = $this->parseForm($result->data['body'])['params'];
        $biz = json_decode($params['biz_content'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($subject, $biz['subject'], '转义只作用于 HTML 层，解码后与原文一致');
        $this->assertSignedByApp($params);
    }

    public function test_wechat_only_trade_type_is_rejected(): void
    {
        $this->expectException(GatewayException::class);

        $this->driver()->create($this->request(TradeType::NATIVE));
    }

    public function test_keys_without_pem_headers_are_accepted(): void
    {
        $strip = static fn (string $pem): string => trim((string) preg_replace('/-----[A-Z ]+-----|\s+/', '', $pem));
        $driver = new AlipayDriver(AlipayStub::config($strip(self::$app['private']), $strip(self::$alipay['public'])));

        $params = $this->parseForm($driver->create($this->request(TradeType::PAGE))->data['body'])['params'];
        $this->assertSignedByApp($params);
    }

    public function test_invalid_private_key_throws_config_exception_without_leaking_key(): void
    {
        try {
            new AlipayDriver(AlipayStub::config('not-a-key-SECRET-MATERIAL', self::$alipay['public']));
            $this->fail('私钥无效必须抛 PaymentConfigException');
        } catch (PaymentConfigException $e) {
            $this->assertStringNotContainsString('SECRET-MATERIAL', $e->getMessage());
        }
    }

    public function test_invalid_alipay_public_key_throws_config_exception(): void
    {
        $this->expectException(PaymentConfigException::class);

        new AlipayDriver(AlipayStub::config(self::$app['private'], 'garbage'));
    }
}
