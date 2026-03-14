<?php
// 事件定义文件
return [
    'bind'      => [
    ],

    'listen'    => [
        'AppInit'  => [],
        'HttpRun'  => [],
        'HttpEnd'  => [],
        'LogLevel' => [],
        'LogWrite' => [],

        // ---- 管理员事件 ----
        'admin.login.success' => [\app\listener\system\AdminLoginSuccessListener::class],
        'admin.login.failed'  => [\app\listener\system\AdminLoginFailedListener::class],

        // ---- 系统配置事件 ----
        'config.changed' => [\app\listener\system\ConfigChangedListener::class],

        // ---- 用户事件 ----
        'user.register' => [\app\listener\user\UserRegisterListener::class],
        'user.login'    => [\app\listener\user\UserLoginListener::class],

        // ---- 支付事件 ----
        'payment.success' => [\app\listener\payment\PaymentSuccessListener::class],

        // ---- 反馈事件 ----
        'feedback.created' => [\app\listener\feedback\FeedbackCreatedListener::class],
    ],

    'subscribe' => [
    ],
];
