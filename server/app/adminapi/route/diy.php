<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * ============================================================ */

use think\facade\Route;

Route::group('diy', function () {
    // 注意：全局 route_complete_match=false，短路由 home 会前缀误匹配 home/summary 等。
    // 必须先注册更具体的 home/*，再注册裸 home。
    Route::get('home/summary', 'v1.diy.DiyPageController/homeSummary');
    Route::post('home/publish', 'v1.diy.DiyPageController/publishHome');
    Route::get('home/versions', 'v1.diy.DiyPageController/versions');
    Route::post('home/versions/:id/restore', 'v1.diy.DiyPageController/restoreVersion');
    // 裸 home 强制完全匹配，避免再吞掉 home/summary、home/versions
    Route::get('home', 'v1.diy.DiyPageController/getHome')->completeMatch();
    Route::put('home', 'v1.diy.DiyPageController/saveHome')->completeMatch();

    Route::get('widgets', 'v1.diy.DiyPageController/widgets');
    Route::get('link-catalog', 'v1.diy.DiyPageController/linkCatalog');

    Route::get('links', 'v1.diy.DiyLinkController/index');
    Route::post('links', 'v1.diy.DiyLinkController/save');
    Route::put('links/:id', 'v1.diy.DiyLinkController/update');
    Route::delete('links/:id', 'v1.diy.DiyLinkController/delete');

    // 带动作的 pages 路由放在 pages/:id 之前，避免被误匹配
    Route::get('pages/:key/summary', 'v1.diy.DiyPageController/pageSummary');
    Route::get('pages/:key/draft', 'v1.diy.DiyPageController/getDraftByKey');
    Route::put('pages/:key/draft', 'v1.diy.DiyPageController/saveDraftByKey');
    Route::post('pages/:key/publish', 'v1.diy.DiyPageController/publishByKey');
    Route::get('pages/:key/versions', 'v1.diy.DiyPageController/versionsByKey');
    Route::post('pages/:key/versions/:id/restore', 'v1.diy.DiyPageController/restoreVersionByKey');

    Route::get('pages', 'v1.diy.DiyPageController/listPages');
    Route::post('pages', 'v1.diy.DiyPageController/createPage');
    Route::post('pages/:id/copy', 'v1.diy.DiyPageController/copyPage');
    Route::put('pages/:id', 'v1.diy.DiyPageController/updatePage');
    Route::delete('pages/:id', 'v1.diy.DiyPageController/deletePage');
})->middleware(['admin_auth', 'admin_permission', 'admin_log']);
