<?php

declare(strict_types=1);

// 支付（M5b）。对客户端只说「能不能用」，不说缺哪项配置——具体原因只进日志。
return [
    'unavailable'     => '支付方式暂不可用',
    'create_failed'   => '支付下单失败，请稍后重试',
    'order_not_found' => '订单不存在',
    'recharge_remark' => '在线充值',
];
