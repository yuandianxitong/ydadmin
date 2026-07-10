<?php
// server/app/adminapi/route/ai_studio.php
use think\facade\Route;

// AI Studio：SSE 流式生成 + 预览/diff/写入/反馈
// 注：stream 为 SSE 长连接，内部以 exit 结束请求，admin_log 中间件在
// $next() 返回后才记录日志——exit 会跳过该后置逻辑，经本地验证不会报错，
// 只是该路由的操作日志不会写入（非报错/非日志损坏，可接受）；apply 等
// 其它端点仍挂 admin_log，保留完整审计。
Route::group('system/ai-studio', function () {
    Route::post('stream', 'v1.system.AiStudioController/stream');
    Route::post('preview', 'v1.system.AiStudioController/preview');
    Route::post('diff', 'v1.system.AiStudioController/diff');
    Route::post('apply', 'v1.system.AiStudioController/apply');
    Route::post('feedback', 'v1.system.AiStudioController/feedback');
})->middleware(['admin_auth', 'admin_permission', 'admin_log']);
