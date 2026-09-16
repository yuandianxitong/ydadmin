<?php

declare(strict_types=1);

namespace tests\Support\Payment;

use core\payment\dto\CreateOrderRequest;
use core\payment\dto\CreateOrderResult;
use core\payment\dto\NotifyAck;
use core\payment\dto\NotifyRequest;
use core\payment\dto\NotifyResult;
use core\payment\dto\RefundRequest;
use core\payment\dto\RefundResult;
use core\payment\dto\TradeQueryResult;
use core\payment\PaymentGatewayInterface;

/**
 * 服务层测试用的假网关：按方法名排队返回值或异常，并记录每次调用的参数。
 * 没排队就被调用直接抛 LogicException——服务「不该调网关却调了」要当场暴露，不能静默返回默认值。
 */
final class FakeGateway implements PaymentGatewayInterface
{
    /** @var array<string, list<mixed>> */
    private array $queues = [];

    /** @var list<array{method: string, args: array<int, mixed>}> */
    private array $calls = [];

    public function queue(string $method, mixed $resultOrThrowable): void
    {
        $this->queues[$method][] = $resultOrThrowable;
    }

    /** @return list<array{method: string, args: array<int, mixed>}> */
    public function calls(): array
    {
        return $this->calls;
    }

    /** @return list<array<int, mixed>> 某个方法每次调用的参数 */
    public function callsTo(string $method): array
    {
        return array_values(array_map(
            static fn (array $call): array => $call['args'],
            array_filter($this->calls, static fn (array $call): bool => $call['method'] === $method)
        ));
    }

    public function create(CreateOrderRequest $request): CreateOrderResult
    {
        /** @var CreateOrderResult */
        return $this->next('create', [$request]);
    }

    public function query(string $orderNo): TradeQueryResult
    {
        /** @var TradeQueryResult */
        return $this->next('query', [$orderNo]);
    }

    public function close(string $orderNo): void
    {
        $this->next('close', [$orderNo]);
    }

    public function refund(RefundRequest $request): RefundResult
    {
        /** @var RefundResult */
        return $this->next('refund', [$request]);
    }

    public function queryRefund(string $orderNo, string $refundNo): RefundResult
    {
        /** @var RefundResult */
        return $this->next('queryRefund', [$orderNo, $refundNo]);
    }

    public function verifyNotify(NotifyRequest $request): NotifyResult
    {
        /** @var NotifyResult */
        return $this->next('verifyNotify', [$request]);
    }

    public function notifyAck(bool $success): NotifyAck
    {
        $this->calls[] = ['method' => 'notifyAck', 'args' => [$success]];

        return $success ? new NotifyAck(200, 'text/plain', 'success') : new NotifyAck(500, 'text/plain', 'fail');
    }

    /** @param array<int, mixed> $args */
    private function next(string $method, array $args): mixed
    {
        $this->calls[] = ['method' => $method, 'args' => $args];
        if (($this->queues[$method] ?? []) === []) {
            throw new \LogicException("FakeGateway::{$method}() 被调用了，但测试没有为它排队返回值");
        }
        $next = array_shift($this->queues[$method]);
        if ($next instanceof \Throwable) {
            throw $next;
        }

        return $next;
    }
}
