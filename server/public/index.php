<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2019 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

use think\App;

// [ 应用入口文件 ]

// 检查系统是否已安装
$installLockFile = __DIR__ . '/../config/install.lock';
if (!file_exists($installLockFile)) {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    // 如果不是访问安装页面，则重定向到安装向导
    if (strpos($requestUri, '/install') === false) {
        // API请求返回JSON提示
        if (strpos($requestUri, '/adminapi') !== false ||
            (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(503);
            echo json_encode([
                'code' => 503,
                'message' => '系统尚未安装',
                'data' => ['installed' => false]
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        // 普通请求重定向到安装页面
        header('Location: /install/');
        exit;
    }
}

require __DIR__ . '/../vendor/autoload.php';

// 执行HTTP应用并响应
$http = (new App())->http;

$response = $http->run();

$response->send();

$http->end($response);