<?php

declare(strict_types=1);

// 支付（M5b）。对客户端只说「能不能用」，不说缺哪项配置——具体原因只进日志。
return [
    'unavailable'     => '支付方式暂不可用',
    'create_failed'   => '支付下单失败，请稍后重试',
    'order_not_found' => '订单不存在',
    'recharge_remark' => '在线充值',
    'client_not_supported' => '当前环境不支持该支付方式',
    'wechat_auth_required' => '请先完成微信授权后再支付',
    'rate_limited'         => '操作过于频繁，请稍后再试',
    'too_many_pending'     => '待支付订单过多，请先完成或等待其过期后再试',
    'recharge_subject'     => '余额充值',
    'refund_remark'               => '充值退款',
    'refund_revert_remark'        => '退款失败冲正',
    'refund_status_invalid'       => '订单状态不允许退款',
    'refund_in_progress'          => '该订单有退款正在处理',
    'refund_exceeds'              => '退款金额超过可退金额',
    'refund_balance_insufficient' => '用户余额不足，无法退回充值',
];
