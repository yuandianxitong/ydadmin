<?php

declare(strict_types=1);

namespace core\payment;

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

/**
 * 支付网关（M5b spec §3）。一个实现对应一个渠道，金额一律整数分。
 *
 * 失败分类靠异常类型（计划设计决定 3）：GatewayException = 明确失败；GatewayResultUnknownException = 结果
 * 不确定（超时、5xx、应答无法解析或验签失败），调用方必须按「可能已成功」处理，不得当失败回滚。
 */
interface PaymentGatewayInterface
{
    /** @throws GatewayException|GatewayResultUnknownException */
    public function create(CreateOrderRequest $request): CreateOrderResult;

    /** @throws GatewayException|GatewayResultUnknownException */
    public function query(string $orderNo): TradeQueryResult;

    /**
     * 关闭订单。渠道侧订单不存在视为成功（用户从未扫码）。
     *
     * @throws GatewayException|GatewayResultUnknownException
     */
    public function close(string $orderNo): void;

    /**
     * 返回 RefundResult::SUCCESS|PROCESSING|FAILED。
     *
     * @throws GatewayResultUnknownException
     */
    public function refund(RefundRequest $request): RefundResult;

    /**
     * 返回 RefundResult::SUCCESS|PROCESSING|FAILED|NOT_FOUND。
     *
     * @throws GatewayResultUnknownException
     */
    public function queryRefund(string $orderNo, string $refundNo): RefundResult;

    /**
     * 验签并解析支付回调。验签通过但不是支付成功事件时返回 paid=false（应答成功、不处理）。
     *
     * @throws NotifyVerificationException
     */
    public function verifyNotify(NotifyRequest $request): NotifyResult;

    public function notifyAck(bool $success): NotifyAck;
}
