<?php

/**
 * 微信基础层与 C 端微信登录（M6a spec §3、§4、§5）。
 *
 * 秒数类超时 ≤0 时代码回退默认值：Guzzle 的 0 表示无限等待，常驻 worker 会被一次外呼永久占住。
 */
return [
    // 微信服务端 API 连接超时 / 读超时（秒）
    'connect_timeout'    => 5.0,
    'timeout'            => 10.0,
    // access_token 刷新锁的持有上限（秒）；未抢到锁的 worker 最多等待 token_wait_ms 毫秒读缓存
    'token_lock_seconds' => 10,
    'token_wait_ms'      => 3000,
    // 同一 openid 「匹配→绑定/注册」的登录锁（秒）
    'login_lock_seconds' => 5,
    // 小程序快捷登录 temp_token 有效期（秒）
    'quick_token_ttl'    => 300,
    // 公众号绑定 cookie 有效期（秒，7 天）
    'oa_bind_cookie_ttl' => 604800,
];
