<?php

declare(strict_types=1);

namespace tests\Unit\Payment;

use core\payment\driver\WechatPayDriver;
use core\payment\dto\RefundRequest;
use core\payment\dto\RefundResult;
use core\payment\dto\TradeQueryResult;
use core\payment\exception\GatewayException;
use core\payment\exception\GatewayResultUnknownException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use tests\Support\Payment\WechatPayFixture;
use tests\TestCase;

final class WechatPayDriverQueryRefundTest extends TestCase
{
    private const ORDER_NO = 'R2026091612000012345678';

    private const REFUND_NO = 'F2026091612300087654321';

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

    private function refundRequest(string $reason = '用户申请退款'): RefundRequest
    {
        return new RefundRequest(self::ORDER_NO, self::REFUND_NO, 500, 1234, $reason);
    }

    private function connectError(): ConnectException
    {
        return new ConnectException('Operation timed out', new Request('GET', 'https://api.mch.weixin.qq.com/'));
    }

    // ---- query

    /** @return array<string, array{string, string}> */
    public static function tradeStates(): array
    {
        return [
            'SUCCESS'    => ['SUCCESS', TradeQueryResult::PAID],
            'REFUND'     => ['REFUND', TradeQueryResult::PAID],
            'NOTPAY'     => ['NOTPAY', TradeQueryResult::PENDING],
            'USERPAYING' => ['USERPAYING', TradeQueryResult::PENDING],
            'PAYERROR'   => ['PAYERROR', TradeQueryResult::PENDING],
            'CLOSED'     => ['CLOSED', TradeQueryResult::CLOSED],
            'REVOKED'    => ['REVOKED', TradeQueryResult::CLOSED],
        ];
    }

    #[DataProvider('tradeStates')]
    public function test_query_maps_trade_state(string $tradeState, string $expected): void
    {
        $payload = ['out_trade_no' => self::ORDER_NO, 'trade_state' => $tradeState, 'amount' => ['total' => 1234]];
        if ($expected === TradeQueryResult::PAID) {
            $payload['transaction_id'] = '4200001234202609160000000001';
        }

        $result = $this->driver([$this->fx->jsonResponse(200, $payload)])->query(self::ORDER_NO);

        $this->assertSame($expected, $result->state);
        if ($expected === TradeQueryResult::PAID) {
            $this->assertSame('4200001234202609160000000001', $result->tradeNo);
            $this->assertSame(1234, $result->paidCents);
        }
    }

    public function test_query_keeps_order_no_case_in_path_and_sends_mchid(): void
    {
        $this->driver([$this->fx->jsonResponse(200, ['trade_state' => 'NOTPAY'])])->query(self::ORDER_NO);

        $uri = $this->history[0]['request']->getUri();
        $this->assertSame('GET', $this->history[0]['request']->getMethod());
        // SDK 的链式段名会把大写字母改写（异议 3），单号必须经 URI 模板占位原样传递
        $this->assertSame('/v3/pay/transactions/out-trade-no/' . self::ORDER_NO, $uri->getPath());
        $this->assertSame('mchid=' . $this->fx->mchId, $uri->getQuery());
    }

    public function test_query_order_not_exist_is_not_found(): void
    {
        $result = $this->driver([$this->fx->errorResponse(404, 'ORDER_NOT_EXIST', '订单不存在')])->query(self::ORDER_NO);

        $this->assertSame(TradeQueryResult::NOT_FOUND, $result->state);
    }

    public function test_query_other_4xx_is_gateway_exception(): void
    {
        $this->expectException(GatewayException::class);

        $this->driver([$this->fx->errorResponse(403, 'NO_AUTH')])->query(self::ORDER_NO);
    }

    public function test_query_unknown_state_is_result_unknown(): void
    {
        $this->expectException(GatewayResultUnknownException::class);

        $this->driver([$this->fx->jsonResponse(200, ['trade_state' => 'SOMETHING_NEW'])])->query(self::ORDER_NO);
    }

    public function test_query_paid_without_amount_is_result_unknown(): void
    {
        $this->expectException(GatewayResultUnknownException::class);

        $this->driver([$this->fx->jsonResponse(200, ['trade_state' => 'SUCCESS', 'transaction_id' => '42'])])->query(self::ORDER_NO);
    }

    public function test_query_5xx_is_result_unknown(): void
    {
        $this->expectException(GatewayResultUnknownException::class);

        $this->driver([$this->fx->errorResponse(503, 'SYSTEM_ERROR')])->query(self::ORDER_NO);
    }

    // ---- close

    public function test_close_posts_mchid_and_accepts_signed_204(): void
    {
        $this->driver([$this->fx->signedResponse(204, '')])->close(self::ORDER_NO);

        $sent = $this->history[0]['request'];
        $this->assertSame('POST', $sent->getMethod());
        $this->assertSame('/v3/pay/transactions/out-trade-no/' . self::ORDER_NO . '/close', $sent->getUri()->getPath());
        $this->assertSame(['mchid' => $this->fx->mchId], json_decode((string) $sent->getBody(), true));
    }

    public function test_close_order_not_exist_counts_as_success(): void
    {
        $this->driver([$this->fx->errorResponse(404, 'ORDER_NOT_EXIST')])->close(self::ORDER_NO);

        $this->assertCount(1, $this->history);
    }

    public function test_close_other_4xx_is_gateway_exception(): void
    {
        $this->expectException(GatewayException::class);

        $this->driver([$this->fx->errorResponse(400, 'ORDER_PAID')])->close(self::ORDER_NO);
    }

    public function test_close_connect_error_is_result_unknown(): void
    {
        $this->expectException(GatewayResultUnknownException::class);

        $this->driver([$this->connectError()])->close(self::ORDER_NO);
    }

    // ---- refund

    public function test_refund_sends_amounts_and_truncates_reason_to_80_bytes(): void
    {
        $reason = str_repeat('退', 40);  // 120 字节
        $this->driver([$this->fx->jsonResponse(200, ['refund_id' => '50300001', 'status' => 'PROCESSING'])])
            ->refund($this->refundRequest($reason));

        $sent = $this->history[0]['request'];
        $this->assertSame('POST', $sent->getMethod());
        $this->assertSame('/v3/refund/domestic/refunds', $sent->getUri()->getPath());
        $body = json_decode((string) $sent->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(self::ORDER_NO, $body['out_trade_no']);
        $this->assertSame(self::REFUND_NO, $body['out_refund_no']);
        $this->assertSame(['refund' => 500, 'total' => 1234, 'currency' => 'CNY'], $body['amount']);
        $this->assertLessThanOrEqual(80, strlen($body['reason']));
        $this->assertTrue(mb_check_encoding($body['reason'], 'UTF-8'));
        $this->assertStringStartsWith('退退退', $body['reason']);
    }

    public function test_refund_omits_empty_reason(): void
    {
        $this->driver([$this->fx->jsonResponse(200, ['refund_id' => '1', 'status' => 'SUCCESS'])])->refund($this->refundRequest(''));

        $body = json_decode((string) $this->history[0]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayNotHasKey('reason', $body);
    }

    /** @return array<string, array{string, string}> */
    public static function refundStatuses(): array
    {
        return [
            'SUCCESS'    => ['SUCCESS', RefundResult::SUCCESS],
            'PROCESSING' => ['PROCESSING', RefundResult::PROCESSING],
            'ABNORMAL'   => ['ABNORMAL', RefundResult::PROCESSING],
            'CLOSED'     => ['CLOSED', RefundResult::FAILED],
        ];
    }

    #[DataProvider('refundStatuses')]
    public function test_refund_maps_status(string $status, string $expected): void
    {
        $result = $this->driver([$this->fx->jsonResponse(200, ['refund_id' => '50300001', 'status' => $status])])
            ->refund($this->refundRequest());

        $this->assertSame($expected, $result->status);
        $this->assertSame('50300001', $result->channelRefundNo);
    }

    public function test_refund_abnormal_carries_error_msg_for_manual_follow_up(): void
    {
        $result = $this->driver([$this->fx->jsonResponse(200, ['refund_id' => '1', 'status' => 'ABNORMAL'])])->refund($this->refundRequest());

        $this->assertSame('ABNORMAL', $result->errorMsg);
    }

    public function test_refund_4xx_is_failed_with_channel_code(): void
    {
        $result = $this->driver([$this->fx->errorResponse(403, 'NOT_ENOUGH', '基本账户余额不足')])->refund($this->refundRequest());

        $this->assertSame(RefundResult::FAILED, $result->status);
        $this->assertStringContainsString('NOT_ENOUGH', (string) $result->errorMsg);
    }

    public function test_refund_5xx_is_result_unknown(): void
    {
        $this->expectException(GatewayResultUnknownException::class);

        $this->driver([$this->fx->errorResponse(500, 'SYSTEM_ERROR')])->refund($this->refundRequest());
    }

    public function test_refund_connect_error_is_result_unknown(): void
    {
        $this->expectException(GatewayResultUnknownException::class);

        $this->driver([$this->connectError()])->refund($this->refundRequest());
    }

    public function test_refund_with_platform_cert_unavailable_is_failed_without_sending(): void
    {
        // 空缓存 + 刚下载失败过（限频标记是新的）：请求根本发不出去，可以确定「没退」
        $fresh = new WechatPayFixture();
        try {
            mkdir($fresh->certCacheDir() . '/' . $fresh->mchId, 0o755, true);
            touch($fresh->certCacheDir() . '/' . $fresh->mchId . '/.refreshed_at');
            $history = [];
            $driver = new WechatPayDriver($fresh->config(), $fresh->handler([], $history));

            $result = $driver->refund($this->refundRequest());

            $this->assertSame(RefundResult::FAILED, $result->status);
            $this->assertSame([], $history);
        } finally {
            $fresh->cleanup();
        }
    }

    // ---- queryRefund

    public function test_query_refund_keeps_refund_no_case_in_path(): void
    {
        $result = $this->driver([$this->fx->jsonResponse(200, ['refund_id' => '50300001', 'status' => 'SUCCESS'])])
            ->queryRefund(self::ORDER_NO, self::REFUND_NO);

        $this->assertSame('GET', $this->history[0]['request']->getMethod());
        $this->assertSame('/v3/refund/domestic/refunds/' . self::REFUND_NO, $this->history[0]['request']->getUri()->getPath());
        $this->assertSame(RefundResult::SUCCESS, $result->status);
    }

    #[DataProvider('refundStatuses')]
    public function test_query_refund_maps_status(string $status, string $expected): void
    {
        $result = $this->driver([$this->fx->jsonResponse(200, ['refund_id' => '1', 'status' => $status])])
            ->queryRefund(self::ORDER_NO, self::REFUND_NO);

        $this->assertSame($expected, $result->status);
    }

    public function test_query_refund_resource_not_exists_is_not_found(): void
    {
        $result = $this->driver([$this->fx->errorResponse(404, 'RESOURCE_NOT_EXISTS', '退款单不存在')])
            ->queryRefund(self::ORDER_NO, self::REFUND_NO);

        $this->assertSame(RefundResult::NOT_FOUND, $result->status);
    }

    public function test_query_refund_other_4xx_is_result_unknown(): void
    {
        // 查询被拒绝不能说明退款失败，交给下一轮对账
        $this->expectException(GatewayResultUnknownException::class);

        $this->driver([$this->fx->errorResponse(403, 'NO_AUTH')])->queryRefund(self::ORDER_NO, self::REFUND_NO);
    }

    public function test_query_refund_unknown_status_is_result_unknown(): void
    {
        $this->expectException(GatewayResultUnknownException::class);

        $this->driver([$this->fx->jsonResponse(200, ['refund_id' => '1', 'status' => 'WHAT'])])->queryRefund(self::ORDER_NO, self::REFUND_NO);
    }
}
