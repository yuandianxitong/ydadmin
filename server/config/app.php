<?php

use support\Request;

return [
    'app_key'           => (string) env('APP_KEY', ''),
    'debug'             => (bool) env('APP_DEBUG', false),
    'error_reporting'   => E_ALL,
    'default_timezone'  => 'Asia/Shanghai',
    'request_class'     => Request::class,
    'public_path'       => base_path() . DIRECTORY_SEPARATOR . 'public',
    'runtime_path'      => base_path(false) . DIRECTORY_SEPARATOR . 'runtime',
    'controller_suffix' => 'Controller',
    'controller_reuse'  => false,
];
