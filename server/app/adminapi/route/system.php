<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
use think\facade\Route;

// 系统管理相关路由
Route::group('system', function () {

    // 管理员管理
    Route::group('admin', function () {
        Route::get('', 'v1.system.AdminController/index');
        Route::get('role/options', 'v1.system.AdminController/roleOptions');
        Route::put('change-password', 'v1.system.AdminController/changePassword');
        Route::post('batch-delete', 'v1.system.AdminController/batchDelete');
        Route::put(':id/status', 'v1.system.AdminController/status');
        Route::put(':id/reset-password', 'v1.system.AdminController/resetPassword');
        Route::get(':id', 'v1.system.AdminController/show');
        Route::post('', 'v1.system.AdminController/store');
        Route::put(':id', 'v1.system.AdminController/update');
        Route::delete(':id', 'v1.system.AdminController/delete');
    });

    // 角色管理
    Route::group('role', function () {
        Route::get('', 'v1.system.RoleController/index');
        Route::get('permission/tree', 'v1.system.RoleController/permissionTree');
        Route::get('menu/tree', 'v1.system.RoleController/menuTree');
        Route::get('options', 'v1.system.RoleController/options');
        Route::post('batch-delete', 'v1.system.RoleController/batchDelete');
        Route::get(':id/permissions', 'v1.system.RoleController/permissions');
        Route::put(':id/assign-permissions', 'v1.system.RoleController/assignPermissions');
        Route::put(':id/status', 'v1.system.RoleController/status');
        Route::get(':id', 'v1.system.RoleController/show');
        Route::post('', 'v1.system.RoleController/store');
        Route::put(':id', 'v1.system.RoleController/update');
        Route::delete(':id', 'v1.system.RoleController/delete');
    });

    // 权限管理
    Route::group('permission', function () {
        Route::get('', 'v1.system.PermissionController/index');
        Route::get('tree', 'v1.system.PermissionController/tree');
        Route::post('', 'v1.system.PermissionController/store');
        Route::put(':id', 'v1.system.PermissionController/update');
        Route::delete(':id', 'v1.system.PermissionController/delete');
        Route::post('batch-create', 'v1.system.PermissionController/batchCreate');
    });

    // 菜单管理
    Route::group('menu', function () {
        Route::get('', 'v1.system.MenuController/index');
        Route::get('options', 'v1.system.MenuController/options');
        Route::get('routes', 'v1.system.MenuController/routes');
        Route::post('', 'v1.system.MenuController/store');
        Route::post('batch-delete', 'v1.system.MenuController/batchDelete');
        Route::post('batch-sort', 'v1.system.MenuController/batchSort');
        Route::put(':id/status', 'v1.system.MenuController/status');
        Route::put(':id', 'v1.system.MenuController/update');
        Route::delete(':id', 'v1.system.MenuController/delete');
    });

    // 日志管理
    Route::group('log', function () {
        Route::get('login', 'v1.system.LogController/loginLog');
        Route::get('operation', 'v1.system.LogController/operationLog');
        Route::delete('login/:id', 'v1.system.LogController/deleteLoginLog');
        Route::delete('operation/:id', 'v1.system.LogController/deleteOperationLog');
        Route::post('login/clear', 'v1.system.LogController/clearLoginLog');
        Route::post('operation/clear', 'v1.system.LogController/clearOperationLog');
    });

    // 数据字典管理
    Route::group('dictionary', function () {
        Route::get('', 'v1.system.DictionaryController/index');
        Route::get('options', 'v1.system.DictionaryController/options');
        Route::get('batch-options', 'v1.system.DictionaryController/batchOptions');
        // 字典项（必须在 :id 之前，否则会被 :id 吞掉）
        Route::get(':id/items', 'v1.system.DictionaryController/items');
        Route::get(':id', 'v1.system.DictionaryController/show');
        Route::post('', 'v1.system.DictionaryController/store');
        Route::put(':id', 'v1.system.DictionaryController/update');
        Route::delete(':id', 'v1.system.DictionaryController/delete');
        Route::post('batch-delete', 'v1.system.DictionaryController/batchDelete');
        Route::post('item', 'v1.system.DictionaryController/storeItem');
        Route::put('item/:id', 'v1.system.DictionaryController/updateItem');
        Route::delete('item/:id', 'v1.system.DictionaryController/deleteItem');
    });

    // 文件管理
    Route::group('file', function () {
        Route::get('', 'v1.system.FileController/index');
        Route::get('groups', 'v1.system.FileController/groups');
        Route::post('move-group', 'v1.system.FileController/moveGroup');
        Route::post('batch-delete', 'v1.system.FileController/batchDelete');
        Route::put(':id/rename', 'v1.system.FileController/rename');
        Route::delete(':id', 'v1.system.FileController/delete');
    });

    // 部门管理
    Route::group('department', function () {
        Route::get('', 'v1.system.DepartmentController/index');
        Route::get('options', 'v1.system.DepartmentController/options');
        Route::get(':id', 'v1.system.DepartmentController/show');
        Route::post('', 'v1.system.DepartmentController/store');
        Route::put(':id/status', 'v1.system.DepartmentController/status');
        Route::put(':id', 'v1.system.DepartmentController/update');
        Route::delete(':id', 'v1.system.DepartmentController/delete');
    });

    // 代码生成器
    Route::group('generator', function () {
        Route::get('tables', 'v1.system.CodeGeneratorController/tables');
        Route::get('columns', 'v1.system.CodeGeneratorController/columns');
        Route::post('preview', 'v1.system.CodeGeneratorController/preview');
        Route::post('generate', 'v1.system.CodeGeneratorController/generate');
    });

    // 通知管理
    Route::group('notification', function () {
        Route::get('', 'v1.system.NotificationController/index');
        Route::get('mine', 'v1.system.NotificationController/mine');
        Route::get('unread-count', 'v1.system.NotificationController/unreadCount');
        Route::post('read-all', 'v1.system.NotificationController/readAll');
        Route::post(':id/read', 'v1.system.NotificationController/read');
        Route::get(':id', 'v1.system.NotificationController/show');
        Route::post('', 'v1.system.NotificationController/store');
        Route::put(':id', 'v1.system.NotificationController/update');
        Route::delete(':id', 'v1.system.NotificationController/delete');
    });

    // 系统配置管理
    Route::group('config', function () {
        Route::get('', 'v1.system.SystemConfigController/index');
        Route::get('groups', 'v1.system.SystemConfigController/groups');
        Route::get('global', 'v1.system.SystemConfigController/global');
        Route::get(':id', 'v1.system.SystemConfigController/show');
        Route::put(':id', 'v1.system.SystemConfigController/update');
        Route::post('batch-update', 'v1.system.SystemConfigController/batchUpdate');
    });

    // 定时任务管理
    Route::group('cron-job', function () {
        Route::get('', 'v1.system.CronJobController/index');
        Route::put(':id/status', 'v1.system.CronJobController/status');
        Route::post(':id/run', 'v1.system.CronJobController/run');
        Route::get(':id/logs', 'v1.system.CronJobController/logs');
        Route::post(':id/clear-logs', 'v1.system.CronJobController/clearLogs');
        Route::get(':id', 'v1.system.CronJobController/show');
        Route::post('', 'v1.system.CronJobController/store');
        Route::put(':id', 'v1.system.CronJobController/update');
        Route::delete(':id', 'v1.system.CronJobController/delete');
    });

})->middleware(['admin_auth', 'admin_permission', 'admin_log']);

// API文档（无需认证）
Route::group('system', function () {
    Route::get('api-doc/openapi.json', 'v1.system.ApiDocController/openapi');
    Route::get('api-doc', 'v1.system.ApiDocController/index');
});
