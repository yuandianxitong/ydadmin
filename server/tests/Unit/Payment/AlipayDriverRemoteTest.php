<?php

declare(strict_types=1);

namespace tests\Unit\Payment;

use core\payment\driver\AlipayDriver;
use core\payment\dto\RefundRequest;
use core\payment\dto\RefundResult;
use core\payment\dto\TradeQueryResult;
use core\payment\exception\GatewayException;
use core\payment\exception\GatewayResultUnknownException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\RequestInterface;
use tests\Support\Payment\AlipayStub;
use tests\Support\Payment\PaymentKeys;
use tests\TestCase;

/**
 * 需要打网关的四个方法：请求形状、应答验签、结果映射与失败分类（spec §5.5–§5.7、计划设计决定 3–6）。
 * 应答全部由生成的「支付宝私钥」真实签名，驱动持有对应公钥——验签走的是真路径。
 */
final class AlipayDriverRemoteTest extends TestCase
{
    /** @var array{private: string, public: string} */
    private static array $app;
    /** @var array{private: string, public: string} */
    private static array $alipay;
    /** @var array{private: string, public: string} */
    private static array $stranger;

    /** @var list<array{request: RequestInterface, options: array<string, mixed>}> */
    private array $history = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$app = PaymentKeys::rsaPair();
        self::$alipay = PaymentKeys::rsaPair();
        self::$stranger = PaymentKeys::rsaPair();
    }

    /** @param list<Response|\Throwable> $queue */
    private function driver(array $queue, bool $sandbox = false): AlipayDriver
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($queue));
        $stack->push(Middleware::history($this->history));

        return new AlipayDriver(AlipayStub::config(self::$app['private'], self::$alipay['public'], $sandbox), $stack);
    }

    /** @param array<string, mixed> $node */
    private function ok(string $method, array $node): Response
    {
        return AlipayStub::response($method, ['code' => '10000', 'msg' => 'Success'] + $node, self::$alipay['private']);
    }

    private function bizError(string $method, string $code, string $subCode): Response
    {
        return AlipayStub::response($method, [
            'code'     => $code,
            'msg'      => 'Business Failed',
            'sub_code' => $subCode,
            'sub_msg'  => '业务失败',
        ], self::$alipay['private']);
    }

    /** @return array<string, string> */
    private function sentParams(int $index = 0): array
    {
        parse_str((string) $this->history[$index]['request']->getBody(), $params);

        /** @var array<string, string> $params */
        return $params;
    }

    private function connectError(): ConnectException
    {
        return new ConnectException('cURL error 28: timed out', new Request('POST', 'https://openapi.alipay.com/gateway.do'));
    }

    // ---- query

    public function test_query_sends_signed_form_post_with_timeouts(): void
    {
        $this->driver([$this->ok('alipay.trade.query', ['trade_status' => 'WAIT_BUYER_PAY', 'out_trade_no' => 'R1'])])->query('R1');

        $this->assertCount(1, $this->history);
        $request = $this->history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://openapi.alipay.com/gateway.do?charset=utf-8', (string) $request->getUri());
        $this->assertStringStartsWith('application/x-www-form-urlencoded', $request->getHeaderLine('Content-Type'));
        $this->assertEquals(5.0, $this->history[0]['options']['connect_timeout']);
        $this->assertEquals(10.0, $this->history[0]['options']['timeout']);

        $params = $this->sentParams();
        $this->assertSame('alipay.trade.query', $params['method']);
        $this->assertSame(['out_trade_no' => 'R1'], json_decode($params['biz_content'], true, 512, JSON_THROW_ON_ERROR));
        $this->assertArrayNotHasKey('notify_url', $params);
        $sign = $params['sign'];
        unset($params['sign']);
        ksort($params);
        $content = implode('&', array_map(static fn (string $k, string $v): string => "{$k}={$v}", array_keys($params), $params));
        $this->assertSame(1, openssl_verify($content, (string) base64_decode($sign, true), self::$app['public'], OPENSSL_ALGO_SHA256));
    }

    public function test_query_uses_sandbox_gateway_when_enabled(): void
    {
        $this->driver([$this->ok('alipay.trade.query', ['trade_status' => 'WAIT_BUYER_PAY'])], true)->query('R1');

        $this->assertSame('https://openapi-sandbox.dl.alipaydev.com/gateway.do?charset=utf-8', (string) $this->history[0]['request']->getUri());
    }

    /** @return iterable<string, array{string, string}> */
    public static function tradeStatuses(): iterable
    {
        yield 'TRADE_SUCCESS' => ['TRADE_SUCCESS', TradeQueryResult::PAID];
        yield 'TRADE_FINISHED' => ['TRADE_FINISHED', TradeQueryResult::PAID];
        yield 'WAIT_BUYER_PAY' => ['WAIT_BUYER_PAY', TradeQueryResult::PENDING];
        yield 'TRADE_CLOSED' => ['TRADE_CLOSED', TradeQueryResult::CLOSED];
    }

    #[DataProvider('tradeStatuses')]
    public function test_query_maps_trade_status(string $tradeStatus, string $expected): void
    {
        $result = $this->driver([$this->ok('alipay.trade.query', [
            'trade_status' => $tradeStatus,
            'trade_no'     => '2026091622001400000000000001',
            'out_trade_no' => 'R1',
            'total_amount' => '12.30',
        ])])->query('R1');

        $this->assertSame($expected, $result->state);
        $this->assertSame('2026091622001400000000000001', $result->tradeNo);
        if ($expected === TradeQueryResult::PAID) {
            $this->assertSame(1230, $result->paidCents);
        } else {
            $this->assertNull($result->paidCents);
        }
    }

    public function test_query_trade_not_exist_is_not_found(): void
    {
        $result = $this->driver([$this->bizError('alipay.trade.query', '40004', 'ACQ.TRADE_NOT_EXIST')])->query('R1');

        $this->assertSame(TradeQueryResult::NOT_FOUND, $result->state);
    }

    public function test_query_signature_placed_before_node_still_verifies(): void
    {
        $response = AlipayStub::signBefore('alipay.trade.query', [
            'code'         => '10000',
            'msg'          => 'Success',
            'trade_status' => 'TRADE_SUCCESS',
            'total_amount' => '1.00',
            'fund_bill_list' => [['amount' => '1.00', 'fund_channel' => 'ALIPAYACCOUNT']],
        ], self::$alipay['private']);

        $result = $this->driver([$response])->query('R1');

        $this->assertSame(TradeQueryResult::PAID, $result->state);
        $this->assertSame(100, $result->paidCents);
    }

    public function test_query_other_4xxxx_business_error_is_definite_failure(): void
    {
        $this->expectException(GatewayException::class);

        $this->driver([$this->bizError('alipay.trade.query', '40004', 'ACQ.INVALID_PARAMETER')])->query('R1');
    }

    /** @return iterable<string, array{\Closure(): (Response|\Throwable)}> */
    public static function uncertainResponses(): iterable
    {
        yield 'connect timeout' => [static fn (): \Throwable => new ConnectException('timed out', new Request('POST', 'https://openapi.alipay.com/gateway.do'))];
        yield 'http 502' => [static fn (): Response => new Response(502, [], 'Bad Gateway')];
        yield 'not json' => [static fn (): Response => new Response(200, [], '<html>maintenance</html>')];
        yield 'unsigned 20000 error_response' => [static fn (): Response => new Response(200, [], '{"error_response":{"code":"20000","msg":"Service Currently Unavailable","sub_code":"isp.unknow-error"}}')];
    }

    public function test_unsigned_gateway_rejection_is_a_definite_failure(): void
    {
        $body = '{"error_response":{"code":"40002","msg":"Invalid Arguments","sub_code":"isv.invalid-signature"}}';

        $refund = $this->driver([new Response(200, [], $body)])->refund(new RefundRequest('R1', 'F1', 100, 1000, '测试'));
        $this->assertSame(RefundResult::FAILED, $refund->status, '凭据配错被网关拒绝的退款业务未执行，必须明确失败以便冲正');

        $this->expectException(GatewayException::class);
        $this->driver([new Response(200, [], $body)])->query('R1');
    }

    /** @param \Closure(): (Response|\Throwable) $make */
    #[DataProvider('uncertainResponses')]
    public function test_query_transport_and_unverifiable_answers_are_uncertain(\Closure $make): void
    {
        $this->expectException(GatewayResultUnknownException::class);

        $this->driver([$make()])->query('R1');
    }

    public function test_query_system_error_and_20000_are_uncertain(): void
    {
        $driver = $this->driver([
            $this->bizError('alipay.trade.query', '40004', 'ACQ.SYSTEM_ERROR'),
            $this->bizError('alipay.trade.query', '20000', 'isp.unknow-error'),
        ]);

        foreach ([1, 2] as $round) {
            try {
                $driver->query('R1');
                $this->fail("第 {$round} 次必须抛 GatewayResultUnknownException");
            } catch (GatewayResultUnknownException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_query_response_signed_by_another_key_is_uncertain(): void
    {
        $forged = AlipayStub::response('alipay.trade.query', ['code' => '10000', 'trade_status' => 'TRADE_SUCCESS', 'total_amount' => '9999.00'], self::$stranger['private']);

        $this->expectException(GatewayResultUnknownException::class);

        $this->driver([$forged])->query('R1');
    }

    public function test_query_tampered_node_is_uncertain(): void
    {
        $genuine = (string) $this->ok('alipay.trade.query', ['trade_status' => 'WAIT_BUYER_PAY', 'total_amount' => '1.00'])->getBody();
        $tampered = str_replace('WAIT_BUYER_PAY', 'TRADE_SUCCESS', $genuine);

        $this->expectException(GatewayResultUnknownException::class);

        $this->driver([new Response(200, [], $tampered)])->query('R1');
    }

    public function test_query_duplicated_response_node_is_uncertain(): void
    {
        $body = '{"alipay_trade_query_response":{"code":"10000"},"alipay_trade_query_response":{"code":"10000"},"sign":"x"}';

        $this->expectException(GatewayResultUnknownException::class);

        $this->driver([new Response(200, [], $body)])->query('R1');
    }

    public function test_query_unknown_trade_status_is_uncertain(): void
    {
        $this->expectException(GatewayResultUnknownException::class);

        $this->driver([$this->ok('alipay.trade.query', ['trade_status' => 'SOMETHING_NEW'])])->query('R1');
    }

    // ---- close

    public function test_close_succeeds_on_10000_and_on_trade_not_exist(): void
    {
        $driver = $this->driver([
            $this->ok('alipay.trade.close', ['out_trade_no' => 'R1']),
            $this->bizError('alipay.trade.close', '40004', 'ACQ.TRADE_NOT_EXIST'),
        ]);

        $driver->close('R1');
        $driver->close('R2');

        $this->assertSame('alipay.trade.close', $this->sentParams(0)['method']);
        $this->assertSame(['out_trade_no' => 'R2'], json_decode($this->sentParams(1)['biz_content'], true, 512, JSON_THROW_ON_ERROR));
    }

    public function test_close_other_business_error_is_definite_failure(): void
    {
        $this->expectException(GatewayException::class);

        $this->driver([$this->bizError('alipay.trade.close', '40004', 'ACQ.TRADE_STATUS_ERROR')])->close('R1');
    }

    public function test_close_timeout_is_uncertain(): void
    {
        $this->expectException(GatewayResultUnknownException::class);

        $this->driver([$this->connectError()])->close('R1');
    }

    // ---- refund

    private function refundRequest(): RefundRequest
    {
        return new RefundRequest('R1', 'F20260916120000654321', 500, 1230, '用户申请退款');
    }

    public function test_refund_sends_out_request_no_and_amount_in_yuan(): void
    {
        $this->driver([$this->ok('alipay.trade.refund', ['fund_change' => 'Y', 'trade_no' => 'T1', 'refund_fee' => '5.00'])])->refund($this->refundRequest());

        $params = $this->sentParams();
        $this->assertSame('alipay.trade.refund', $params['method']);
        $this->assertSame([
            'out_trade_no'   => 'R1',
            'refund_amount'  => '5.00',
            'out_request_no' => 'F20260916120000654321',
            'refund_reason'  => '用户申请退款',
        ], json_decode($params['biz_content'], true, 512, JSON_THROW_ON_ERROR));
    }

    public function test_refund_fund_change_y_is_success_and_n_is_processing(): void
    {
        $driver = $this->driver([
            $this->ok('alipay.trade.refund', ['fund_change' => 'Y', 'trade_no' => 'T1']),
            $this->ok('alipay.trade.refund', ['fund_change' => 'N', 'trade_no' => 'T1']),
        ]);

        $first = $driver->refund($this->refundRequest());
        $second = $driver->refund($this->refundRequest());

        $this->assertSame(RefundResult::SUCCESS, $first->status);
        $this->assertSame('T1', $first->channelRefundNo);
        $this->assertSame(RefundResult::PROCESSING, $second->status);
    }

    public function test_refund_business_error_is_failed_with_message(): void
    {
        $result = $this->driver([$this->bizError('alipay.trade.refund', '40004', 'ACQ.SELLER_BALANCE_NOT_ENOUGH')])->refund($this->refundRequest());

        $this->assertSame(RefundResult::FAILED, $result->status);
        $this->assertStringContainsString('ACQ.SELLER_BALANCE_NOT_ENOUGH', (string) $result->errorMsg);
    }

    public function test_refund_uncertain_outcomes_throw(): void
    {
        $driver = $this->driver([
            $this->connectError(),
            $this->bizError('alipay.trade.refund', '20000', 'isp.unknow-error'),
            $this->bizError('alipay.trade.refund', '40004', 'ACQ.SYSTEM_ERROR'),
            new Response(504, [], ''),
        ]);

        foreach ([1, 2, 3, 4] as $round) {
            try {
                $driver->refund($this->refundRequest());
                $this->fail("第 {$round} 次必须抛 GatewayResultUnknownException");
            } catch (GatewayResultUnknownException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    // ---- queryRefund

    public function test_query_refund_request_shape(): void
    {
        $this->driver([$this->ok('alipay.trade.fastpay.refund.query', ['refund_status' => 'REFUND_SUCCESS'])])->queryRefund('R1', 'F1');

        $params = $this->sentParams();
        $this->assertSame('alipay.trade.fastpay.refund.query', $params['method']);
        $this->assertSame(['out_trade_no' => 'R1', 'out_request_no' => 'F1'], json_decode($params['biz_content'], true, 512, JSON_THROW_ON_ERROR));
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function refundQueryNodes(): iterable
    {
        yield 'refund_status REFUND_SUCCESS' => [['refund_status' => 'REFUND_SUCCESS', 'out_request_no' => 'F1', 'refund_amount' => '5.00', 'trade_no' => 'T1'], RefundResult::SUCCESS];
        yield 'empty refund_status but data returned' => [['out_request_no' => 'F1', 'refund_amount' => '5.00', 'trade_no' => 'T1'], RefundResult::SUCCESS];
        yield 'other refund_status' => [['refund_status' => 'REFUND_PROCESSING', 'out_request_no' => 'F1'], RefundResult::PROCESSING];
        yield 'no data at all' => [[], RefundResult::NOT_FOUND];
    }

    /** @param array<string, mixed> $node */
    #[DataProvider('refundQueryNodes')]
    public function test_query_refund_maps_result(array $node, string $expected): void
    {
        $result = $this->driver([$this->ok('alipay.trade.fastpay.refund.query', $node)])->queryRefund('R1', 'F1');

        $this->assertSame($expected, $result->status);
    }

    public function test_query_refund_trade_not_exist_is_not_found(): void
    {
        $result = $this->driver([$this->bizError('alipay.trade.fastpay.refund.query', '40004', 'ACQ.TRADE_NOT_EXIST')])->queryRefund('R1', 'F1');

        $this->assertSame(RefundResult::NOT_FOUND, $result->status);
    }

    public function test_query_refund_other_errors_are_uncertain_not_failed(): void
    {
        $driver = $this->driver([
            $this->bizError('alipay.trade.fastpay.refund.query', '40004', 'ACQ.INVALID_PARAMETER'),
            $this->connectError(),
        ]);

        foreach ([1, 2] as $round) {
            try {
                $driver->queryRefund('R1', 'F1');
                $this->fail("第 {$round} 次必须抛 GatewayResultUnknownException——查询失败不等于退款失败");
            } catch (GatewayResultUnknownException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
