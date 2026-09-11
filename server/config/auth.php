<?php

return [
    'jwt' => [
        'algorithm'      => 'HS256',
        'expire'         => 86400,   // 24 小时（无操作即过期）
        'refresh_expire' => 604800,  // 7 天（自 login_at 起的绝对登录时长上限）
        'admin' => [
            'key'    => env('JWT_ADMIN_SECRET', ''),
            'issuer' => 'ydadmin-admin',
        ],
        'user' => [
            'key'    => env('JWT_USER_SECRET', ''),
            'issuer' => 'ydadmin-user',
        ],
    ],
];
