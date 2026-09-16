<?php

declare(strict_types=1);

namespace tests\Unit\Payment;

use core\exception\BusinessException;
use core\payment\exception\GatewayResultUnknownException;
use core\payment\ExceptionLogContext;
use Illuminate\Database\QueryException;
use tests\TestCase;

/** 支付日志只记「按设计不含敏感数据」的异常消息；数据库异常消息带 SQL 绑定值，只记类名与 SQLSTATE。 */
final class ExceptionLogContextTest extends TestCase
{
    public function test_payment_and_business_exceptions_keep_their_message(): void
    {
        $this->assertSame(
            ['exception' => GatewayResultUnknownException::class, 'reason' => '支付宝应答验签失败'],
            ExceptionLogContext::of(new GatewayResultUnknownException('支付宝应答验签失败')),
        );
        $this->assertSame(
            ['exception' => BusinessException::class, 'reason' => '订单不存在'],
            ExceptionLogContext::of(new BusinessException('订单不存在')),
        );
    }

    public function test_other_throwables_log_only_class_and_code(): void
    {
        $pdo = new class ('SQLSTATE[23000]: Integrity constraint violation') extends \PDOException {
            /** @var string */
            protected $code = '23000';
        };
        $query = new QueryException('mysql', 'insert into t values (?)', ['o-secret-openid'], $pdo);

        $this->assertSame(['exception' => QueryException::class, 'code' => '23000'], ExceptionLogContext::of($query));
        $this->assertSame(['exception' => \RuntimeException::class, 'code' => 7], ExceptionLogContext::of(new \RuntimeException('secret', 7)));
    }
}
