<?php
use think\facade\Route;

// 微信公众号服务端回调（公开路由，微信服务器调用）
Route::any('wechat/serve', 'v1.wechat.WechatController/serve');

// 微信公众号 OAuth（无需登录）
Route::group('wechat', function () {
    Route::get('oauth-url', 'v1.wechat.WechatController/oauthUrl');
    Route::get('oauth-callback', 'v1.wechat.WechatController/oauthCallback');
    Route::get('get-openid', 'v1.wechat.WechatController/getOpenid');
});

// 需要登录的微信接口
Route::group('wechat', function () {
    Route::post('decrypt-phone', 'v1.wechat.WechatController/decryptPhone');
})->middleware(['api_auth']);
