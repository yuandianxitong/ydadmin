<?php
use think\facade\Route;

// C端协议（无需登录）
Route::get('agreement/:code', 'v1.agreement.AgreementController/getByCode');
