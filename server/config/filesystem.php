<?php

declare(strict_types=1);

/**
 * 存储磁盘配置。只声明本地磁盘——云驱动（aliyun/tencent/qiniu）的凭据放在 `system_configs`
 * 的 `storage` 分组里，由 `core\storage\StorageManager` 读取，不走本文件。
 *
 * public 盘的 root 与 `config/static.php` 的 public 静态直出同根，url 前缀 `/storage`
 * 与 `core\storage\driver\LocalDriver::getUrl()` 逐字一致。
 */
return [
    'default' => 'local',
    'disks'   => [
        'local'  => [
            'type' => 'local',
            'root' => runtime_path() . '/storage',
        ],
        'public' => [
            'type'       => 'local',
            'root'       => public_path('storage'),
            'url'        => '/storage',
            'visibility' => 'public',
        ],
    ],
];
