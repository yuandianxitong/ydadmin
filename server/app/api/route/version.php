<?php
use think\facade\Route;

// C端版本检查（无需登录）
Route::get('version/check', 'v1.version.VersionController/check');
