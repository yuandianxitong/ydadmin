<?php

/**
 * 支付（M5b spec §3、§5）。凭据不在这里：商户号、密钥等由管理端 payment 组系统配置维护，改了即时生效。
 * 这里只放部署期固定的运行参数。
 */
return [
    // 订单支付时限（分钟）：下单时同时传给渠道（微信 time_expire / 支付宝 time_expire）
    'order_expire_minutes'          => 30,
    // C 端 query 对同一 pending 订单补查渠道的最小间隔（秒）
    'query_throttle_seconds'        => 10,
    // 同一会员每分钟最多下单次数，超出 code 429
    'recharge_per_minute'           => 10,
    // 渠道 HTTP 连接超时 / 读超时（秒）。必须大于 0：Guzzle 的 0 表示无限等待，常驻 worker 会被永久占住
    'connect_timeout'               => 5.0,
    'timeout'                       => 10.0,
    // 微信平台证书缓存根目录，实际目录按商户号分：{wechat_cert_dir}/{mch_id}/
    'wechat_cert_dir'               => runtime_path('cert/wechatpay'),
    // 回调遇到未知序列号时重新下载平台证书的最小间隔（秒），按缓存目录下 .refreshed_at 的 mtime 判断
    'wechat_cert_refresh_interval'  => 60,
    // 关单任务：单轮最多处理条数、过期后的宽限秒数
    'close_batch'                   => 100,
    'close_grace_seconds'           => 60,
    // 退款对账任务：单轮最多处理条数、退款单最少存在多久才查询（秒）
    'reconcile_batch'               => 100,
    'reconcile_min_age_seconds'     => 120,
    // 渠道查无此退款时，退款单创建超过该秒数才判失败并冲正
    'refund_not_found_fail_seconds' => 1800,
    // 退款处理中超过该秒数记 error 日志，提醒人工介入
    'refund_stuck_alert_seconds'    => 86400,
];
