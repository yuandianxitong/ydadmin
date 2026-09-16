<?php

declare(strict_types=1);

namespace tests\Unit\Payment;

use core\payment\driver\WechatPayDriver;
use core\payment\dto\CreateOrderRequest;
use core\payment\exception\GatewayException;
use core\payment\exception\GatewayResultUnknownException;
use core\payment\TradeType;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use tests\Support\Payment\WechatPayFixture;
use tests\TestCase;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Formatter;

final class WechatPayDriverCreateTest extends TestCase
{
    private const ORDER_NO = 'R2026091612000012345678';

    private const NOTIFY_URL = 'https://shop.example.com/api/payment/notify/wechat';

    private WechatPayFixture $fx;

    /** @var array<int, array<string, mixed>> */
    private array $history = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->fx = new WechatPayFixture();
        $this->fx->seedCertCache();
        $this->history = [];
    }

    protected function tearDown(): void
    {
        $this->fx->cleanup();
        parent::tearDown();
    }

    /** @param list<Response|\Throwable> $queue */
    private function driver(array $queue): WechatPayDriver
    {
        return new WechatPayDriver($this->fx->config(), $this->fx->handler($queue, $this->history));
    }

    private function request(string $tradeType, ?string $openid = null, ?string $clientIp = null): CreateOrderRequest
    {
        return new CreateOrderRequest(
            self::ORDER_NO,
            $tradeType,
            '余额充值',
            1234,
            new \DateTimeImmutable('2026-09-16 04:30:00', new \DateTimeZone('UTC')),
            self::NOTIFY_URL,
            $openid,
            $clientIp,
        );
    }

    /** @return array<string, mixed> */
    private function sentJson(int $index = 0): array
    {
        return json_decode((string) $this->history[$index]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed> 下单请求体里各 trade_type 共有的部分 */
    private function commonBody(): array
    {
        return [
            'appid'        => WechatPayFixture::APP_ID,
            'mchid'        => $this->fx->mchId,
            'description'  => '余额充值',
            'out_trade_no' => self::ORDER_NO,
            'time_expire'  => '2026-09-16T12:30:00+08:00',
            'notify_url'   => self::NOTIFY_URL,
            'amount'       => ['total' => 1234, 'currency' => 'CNY'],
        ];
    }

    public function test_native_posts_signed_request_with_timeouts_and_returns_code_url(): void
    {
        $result = $this->driver([$this->fx->jsonResponse(200, ['code_url' => 'weixin://wxpay/bizpayurl?pr=abc'])])
            ->create($this->request(TradeType::NATIVE));

        $this->assertSame(TradeType::NATIVE, $result->tradeType);
        $this->assertSame(['code_url' => 'weixin://wxpay/bizpayurl?pr=abc'], $result->data);

        $sent = $this->history[0]['request'];
        $this->assertSame('POST', $sent->getMethod());
        $this->assertSame('/v3/pay/transactions/native', $sent->getUri()->getPath());
        $this->assertEquals($this->commonBody(), $this->sentJson());
        $this->assertSame(10.0, $this->history[0]['options']['timeout']);
        $this->assertSame(5.0, $this->history[0]['options']['connect_timeout']);

        // Authorization 头用商户私钥签名，可用商户公钥验
        $auth = $sent->getHeaderLine('Authorization');
        $this->assertStringStartsWith(
            'WECHATPAY2-SHA256-RSA2048 mchid="' . $this->fx->mchId . '",serial_no="' . $this->fx->merchantSerial . '"',
            $auth,
        );
        preg_match('/timestamp="(\d+)",nonce_str="([^"]+)",signature="([^"]+)"/', $auth, $m);
        $message = Formatter::request('POST', '/v3/pay/transactions/native', $m[1], $m[2], (string) $sent->getBody());
        $this->assertTrue(Rsa::verify($message, $m[3], Rsa::from($this->fx->merchantPublicPem, Rsa::KEY_TYPE_PUBLIC)));
    }

    public function test_h5_sends_scene_info_and_returns_h5_url(): void
    {
        $result = $this->driver([$this->fx->jsonResponse(200, ['h5_url' => 'https://wx.tenpay.com/cgi-bin/mmpayweb-bin/checkmweb?prepay_id=wx1'])])
            ->create($this->request(TradeType::H5, clientIp: '203.0.113.9'));

        $this->assertSame('/v3/pay/transactions/h5', $this->history[0]['request']->getUri()->getPath());
        $this->assertEquals(
            $this->commonBody() + ['scene_info' => ['payer_client_ip' => '203.0.113.9', 'h5_info' => ['type' => 'Wap']]],
            $this->sentJson(),
        );
        $this->assertSame(['h5_url' => 'https://wx.tenpay.com/cgi-bin/mmpayweb-bin/checkmweb?prepay_id=wx1'], $result->data);
    }

    public function test_jsapi_sends_payer_and_returns_verifiable_invoke_params(): void
    {
        $result = $this->driver([$this->fx->jsonResponse(200, ['prepay_id' => 'wx201410272009395522657a690389285100'])])
            ->create($this->request(TradeType::JSAPI, openid: 'oUpF8uMuAJO_M2pxb1Q9zNjWeS6o'));

        $this->assertSame('/v3/pay/transactions/jsapi', $this->history[0]['request']->getUri()->getPath());
        $this->assertEquals($this->commonBody() + ['payer' => ['openid' => 'oUpF8uMuAJO_M2pxb1Q9zNjWeS6o']], $this->sentJson());

        $data = $result->data;
        $this->assertSame(['appId', 'timeStamp', 'nonceStr', 'package', 'signType', 'paySign'], array_keys($data));
        $this->assertSame(WechatPayFixture::APP_ID, $data['appId']);
        $this->assertSame('prepay_id=wx201410272009395522657a690389285100', $data['package']);
        $this->assertSame('RSA', $data['signType']);
        $this->assertMatchesRegularExpression('/^\d+$/', $data['timeStamp']);
        $this->assertTrue(Rsa::verify(
            Formatter::joinedByLineFeed($data['appId'], $data['timeStamp'], $data['nonceStr'], $data['package']),
            $data['paySign'],
            Rsa::from($this->fx->merchantPublicPem, Rsa::KEY_TYPE_PUBLIC),
        ));
    }

    public function test_app_returns_lowercase_invoke_params_with_verifiable_sign(): void
    {
        $result = $this->driver([$this->fx->jsonResponse(200, ['prepay_id' => 'wx2014'])])
            ->create($this->request(TradeType::APP));

        $this->assertSame('/v3/pay/transactions/app', $this->history[0]['request']->getUri()->getPath());
        $this->assertEquals($this->commonBody(), $this->sentJson());

        $data = $result->data;
        $this->assertSame(['appid', 'partnerid', 'prepayid', 'package', 'noncestr', 'timestamp', 'sign'], array_keys($data));
        $this->assertSame($this->fx->mchId, $data['partnerid']);
        $this->assertSame('wx2014', $data['prepayid']);
        $this->assertSame('Sign=WXPay', $data['package']);
        $this->assertTrue(Rsa::verify(
            Formatter::joinedByLineFeed($data['appid'], $data['timestamp'], $data['noncestr'], $data['prepayid']),
            $data['sign'],
            Rsa::from($this->fx->merchantPublicPem, Rsa::KEY_TYPE_PUBLIC),
        ));
    }

    public function test_jsapi_without_openid_fails_before_sending(): void
    {
        try {
            $this->driver([])->create($this->request(TradeType::JSAPI));
            $this->fail('缺 openid 应抛 GatewayException');
        } catch (GatewayException) {
        }
        $this->assertSame([], $this->history);
    }

    public function test_h5_without_client_ip_fails_before_sending(): void
    {
        try {
            $this->driver([])->create($this->request(TradeType::H5));
            $this->fail('缺客户端 IP 应抛 GatewayException');
        } catch (GatewayException) {
        }
        $this->assertSame([], $this->history);
    }

    public function test_unsupported_trade_type_fails_before_sending(): void
    {
        try {
            $this->driver([])->create($this->request(TradeType::PAGE));
            $this->fail('page 不是微信支付的交易类型');
        } catch (GatewayException) {
        }
        $this->assertSame([], $this->history);
    }

    public function test_4xx_is_gateway_exception_with_channel_code(): void
    {
        $this->expectException(GatewayException::class);
        $this->expectExceptionMessageMatches('/PARAM_ERROR/');

        $this->driver([$this->fx->errorResponse(400, 'PARAM_ERROR', '参数错误')])->create($this->request(TradeType::NATIVE));
    }

    public function test_5xx_is_result_unknown(): void
    {
        $this->expectException(GatewayResultUnknownException::class);

        $this->driver([$this->fx->errorResponse(500, 'SYSTEM_ERROR')])->create($this->request(TradeType::NATIVE));
    }

    public function test_connect_timeout_is_result_unknown(): void
    {
        $this->expectException(GatewayResultUnknownException::class);

        $this->driver([new ConnectException('Connection timed out', new Request('POST', 'https://api.mch.weixin.qq.com/v3/pay/transactions/native'))])
            ->create($this->request(TradeType::NATIVE));
    }

    public function test_tampered_response_body_is_result_unknown(): void
    {
        $signed = $this->fx->jsonResponse(200, ['code_url' => 'weixin://wxpay/bizpayurl?pr=abc']);
        $tampered = new Response(200, $signed->getHeaders(), '{"code_url":"weixin://wxpay/bizpayurl?pr=evil"}');

        $this->expectException(GatewayResultUnknownException::class);
        $this->driver([$tampered])->create($this->request(TradeType::NATIVE));
    }

    public function test_2xx_without_expected_field_is_result_unknown(): void
    {
        $this->expectException(GatewayResultUnknownException::class);

        $this->driver([$this->fx->jsonResponse(200, ['unexpected' => true])])->create($this->request(TradeType::NATIVE));
    }
}
