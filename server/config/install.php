<?php

/**
 * 安装锁。向导或 db:reset 成功后写入 lock 指向的文件。
 * 文件不存在，且库里没有安装记录、.env 里也没有安装时写入的 JWT 密钥时，
 * InstallGuardMiddleware 把页面请求转到 /install/。
 */
return [
    'lock' => base_path('config/install.lock'),
];
