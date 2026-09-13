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
    'max_package_size' => 100 * 1024 * 1024
];
