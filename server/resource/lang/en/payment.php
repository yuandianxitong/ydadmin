<?php

declare(strict_types=1);

return [
    'unavailable'     => 'This payment method is currently unavailable',
    'create_failed'   => 'Failed to create the payment, please try again later',
    'order_not_found' => 'Order not found',
    'recharge_remark' => 'Online recharge',
    'client_not_supported' => 'This payment method is not supported in the current environment',
    'wechat_auth_required' => 'Please complete WeChat authorization before paying',
    'rate_limited'         => 'Too many attempts, please try again later',
    'too_many_pending'     => 'Too many unpaid orders, please complete them or wait for them to expire and try again',
    'recharge_subject'     => 'Balance recharge',
    'refund_remark'               => 'Recharge refund',
    'refund_revert_remark'        => 'Refund failure reversal',
    'refund_status_invalid'       => 'The order status does not allow a refund',
    'refund_in_progress'          => 'A refund for this order is already being processed',
    'refund_exceeds'              => 'The refund amount exceeds the refundable amount',
    'refund_balance_insufficient' => "The user's balance is insufficient to take back this recharge",
];
