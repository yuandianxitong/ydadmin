<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

return [
    'event_loop' => '',
    'stop_timeout' => 2,
    'pid_file' => runtime_path() . '/webman.pid',
    'status_file' => runtime_path() . '/webman.status',
    'stdout_file' => runtime_path() . '/logs/stdout.log',
    'log_file' => runtime_path() . '/logs/workerman.log',
    // M1c：storage_upload_max_size / storage_image_max_size 是管理员可改的上传大小上限（MB）。
    // Workerman 在应用代码跑之前就按这个上限拦请求——包体超限时连接直接被断开（客户端看到的是
    // 连接重置，不是本地化的错误文案），所以它必须留出比「管理员能填的上限」大得多的余量，
    // 而不是刚好等于种子默认值（10MB）：旧值 10*1024*1024 与种子的 10MB 文件上限刚好相等，
    // 一个 10MB 整的文件连同 multipart 头一起发送就已经超限。上限本身由
    // SystemConfigService::assertUploadSizeWithinPackageLimit() 按这个值反过来算，两处不会走漏。
    // 代价要清楚：这是**每连接**的内存上限，不是全局上限——Workerman 在应用代码跑起来之前就把整个
    // 请求体缓冲进内存，而本分支没有任何地方限制上传并发，所以理论峰值是「并发大文件上传数 × 100 MiB」
    // 常驻内存。调高它就是拿 worker 的内存换大文件上传能力；真要收紧，正确的位置是在 nginx 侧限流/限并发，
    // 而不是把这个值压回去（压回去只会让合规的大文件在应用报错之前先被断连）。
    'max_package_size' => 100 * 1024 * 1024
];
