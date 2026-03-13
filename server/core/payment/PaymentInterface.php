<?php
declare(strict_types=1);

namespace core\payment;

/**
 * 统一支付驱动接口
 */
interface PaymentInterface
{
    /**
     * 创建支付订单
     * @param array $order 订单信息 [out_trade_no, total_amount, subject, body, notify_url, ...]
     * @return array 支付参数（不同驱动返回不同结构）
     */
    public function create(array $order): array;

    /**
     * 查询订单
     * @param string $outTradeNo 商户订单号
     * @return array 订单信息
     */
    public function query(string $outTradeNo): array;

    /**
     * 退款
     * @param array $refund [out_trade_no, refund_amount, total_amount, out_refund_no, reason]
     * @return array 退款结果
     */
    public function refund(array $refund): array;

    /**
     * 验证回调通知签名
     * @param array $params 回调参数
     * @return array 验签后的订单数据 [out_trade_no, trade_no, total_amount, status]
     */
    public function verifyNotify(array $params): array;

    /**
     * 回调成功响应
     * @return string
     */
    public function successResponse(): string;

    /**
     * 获取驱动标识
     * @return string
     */
    public function getDriver(): string;
}
