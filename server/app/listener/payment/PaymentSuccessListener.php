<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\listener\payment;

use think\facade\Log;

/**
 * 支付成功监听器
 *
 * 事件数据：
 * - order_no: string   商户订单号
 * - trade_no: string   第三方交易号
 * - amount: string     支付金额
 * - channel: string    支付渠道 alipay / wechat
 *
 * 扩展点：可在此添加发货、通知、积分奖励等后续业务逻辑
 */
class PaymentSuccessListener
{
    public function handle(array $event): void
    {
        Log::info('支付成功', [
            'order_no' => $event['order_no'],
            'trade_no' => $event['trade_no'],
            'amount'   => $event['amount'],
            'channel'  => $event['channel'],
        ]);

        // TODO: 更新业务订单状态
        // TODO: 发送支付成功通知
        // TODO: 赠送积分/优惠券
    }
}
