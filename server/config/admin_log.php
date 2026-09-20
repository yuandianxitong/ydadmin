<?php

/**
 * 操作日志动作文案（spec §6.2）。
 *
 * actions：键为「短类名@方法」，值为 [action 的 lang 键, description 的 lang 键]，
 *   AdminLogMiddleware 写日志时按当次请求的 locale 用 lang() 解析。覆盖 M1a、M1b、M1c 全部写接口
 *   （含 FileController 与 UploadController）。未收录的落到 messages.operation / messages.execute_operation。
 *   新增写接口必须同时登记——tests/Feature/System/AdminLogMiddlewareTest 会逐条核对路由表（也会找出对不上路由的键）。
 * masked_params：键名看不出敏感、但值可能是凭据的参数，按动作整字段脱敏。
 */
return [
    'actions' => [
        // 认证（登录是公开路由，不经本中间件）
        'AuthController@refresh' => ['admin_log.auth_refresh', 'admin_log.auth_refresh_desc'],
        'AuthController@logout'  => ['admin_log.auth_logout', 'admin_log.auth_logout_desc'],

        // 管理员
        'AdminController@store'          => ['admin_log.admin_create', 'admin_log.admin_create_desc'],
        'AdminController@update'         => ['admin_log.admin_update', 'admin_log.admin_update_desc'],
        'AdminController@delete'         => ['admin_log.admin_delete', 'admin_log.admin_delete_desc'],
        'AdminController@batchDelete'    => ['admin_log.admin_batch_delete', 'admin_log.admin_batch_delete_desc'],
        'AdminController@status'         => ['admin_log.admin_status', 'admin_log.admin_status_desc'],
        'AdminController@resetPassword'  => ['admin_log.admin_reset_password', 'admin_log.admin_reset_password_desc'],
        'AdminController@changePassword' => ['admin_log.admin_change_password', 'admin_log.admin_change_password_desc'],

        // 角色
        'RoleController@store'             => ['admin_log.role_create', 'admin_log.role_create_desc'],
        'RoleController@update'            => ['admin_log.role_update', 'admin_log.role_update_desc'],
        'RoleController@delete'            => ['admin_log.role_delete', 'admin_log.role_delete_desc'],
        'RoleController@batchDelete'       => ['admin_log.role_batch_delete', 'admin_log.role_batch_delete_desc'],
        'RoleController@assignPermissions' => ['admin_log.role_assign_permissions', 'admin_log.role_assign_permissions_desc'],
        'RoleController@status'            => ['admin_log.role_status', 'admin_log.role_status_desc'],

        // 菜单
        'MenuController@store'       => ['admin_log.menu_create', 'admin_log.menu_create_desc'],
        'MenuController@update'      => ['admin_log.menu_update', 'admin_log.menu_update_desc'],
        'MenuController@delete'      => ['admin_log.menu_delete', 'admin_log.menu_delete_desc'],
        'MenuController@batchDelete' => ['admin_log.menu_batch_delete', 'admin_log.menu_batch_delete_desc'],
        'MenuController@status'      => ['admin_log.menu_status', 'admin_log.menu_status_desc'],
        'MenuController@batchSort'   => ['admin_log.menu_batch_sort', 'admin_log.menu_batch_sort_desc'],

        // 部门
        'DepartmentController@store'  => ['admin_log.department_create', 'admin_log.department_create_desc'],
        'DepartmentController@update' => ['admin_log.department_update', 'admin_log.department_update_desc'],
        'DepartmentController@status' => ['admin_log.department_status', 'admin_log.department_status_desc'],
        'DepartmentController@delete' => ['admin_log.department_delete', 'admin_log.department_delete_desc'],

        // 系统配置
        'SystemConfigController@update'      => ['admin_log.config_update', 'admin_log.config_update_desc'],
        'SystemConfigController@batchUpdate' => ['admin_log.config_batch_update', 'admin_log.config_batch_update_desc'],
        'SystemConfigController@clearCache'  => ['admin_log.config_clear_cache', 'admin_log.config_clear_cache_desc'],

        // 数据字典
        'DictionaryController@store'       => ['admin_log.dictionary_create', 'admin_log.dictionary_create_desc'],
        'DictionaryController@update'      => ['admin_log.dictionary_update', 'admin_log.dictionary_update_desc'],
        'DictionaryController@delete'      => ['admin_log.dictionary_delete', 'admin_log.dictionary_delete_desc'],
        'DictionaryController@batchDelete' => ['admin_log.dictionary_batch_delete', 'admin_log.dictionary_batch_delete_desc'],
        'DictionaryController@storeItem'   => ['admin_log.dictionary_item_create', 'admin_log.dictionary_item_create_desc'],
        'DictionaryController@updateItem'  => ['admin_log.dictionary_item_update', 'admin_log.dictionary_item_update_desc'],
        'DictionaryController@deleteItem'  => ['admin_log.dictionary_item_delete', 'admin_log.dictionary_item_delete_desc'],

        // 日志
        'LogController@deleteLoginLog'     => ['admin_log.log_delete_login', 'admin_log.log_delete_login_desc'],
        'LogController@deleteOperationLog' => ['admin_log.log_delete_operation', 'admin_log.log_delete_operation_desc'],
        'LogController@clearLoginLog'      => ['admin_log.log_clear_login', 'admin_log.log_clear_login_desc'],
        'LogController@clearOperationLog'  => ['admin_log.log_clear_operation', 'admin_log.log_clear_operation_desc'],

        // 站内通知
        'NotificationController@store'   => ['admin_log.notification_create', 'admin_log.notification_create_desc'],
        'NotificationController@update'  => ['admin_log.notification_update', 'admin_log.notification_update_desc'],
        'NotificationController@delete'  => ['admin_log.notification_delete', 'admin_log.notification_delete_desc'],
        'NotificationController@read'    => ['admin_log.notification_read', 'admin_log.notification_read_desc'],
        'NotificationController@readAll' => ['admin_log.notification_read_all', 'admin_log.notification_read_all_desc'],

        // 素材与上传（M1c）
        'FileController@moveGroup'   => ['admin_log.file_move_group', 'admin_log.file_move_group_desc'],
        'FileController@rename'      => ['admin_log.file_rename', 'admin_log.file_rename_desc'],
        'FileController@delete'      => ['admin_log.file_delete', 'admin_log.file_delete_desc'],
        'FileController@batchDelete' => ['admin_log.file_batch_delete', 'admin_log.file_batch_delete_desc'],
        'UploadController@image'     => ['admin_log.upload_image', 'admin_log.upload_image_desc'],
        'UploadController@file'      => ['admin_log.upload_file', 'admin_log.upload_file_desc'],

        // 代码生成器（M2a）。两条都是 POST，都经操作日志中间件：generate 会往磁盘写 PHP 文件，
        // preview 虽然只渲染不落盘，但它同样暴露生成内容，两条都要留下审计痕迹。
        'GeneratorController@preview'  => ['admin_log.generator_preview', 'admin_log.generator_preview_desc'],
        'GeneratorController@generate' => ['admin_log.generator_generate', 'admin_log.generator_generate_desc'],

        // 定时任务（M3）
        'CronJobController@store'     => ['admin_log.cron_job_create', 'admin_log.cron_job_create_desc'],
        'CronJobController@update'    => ['admin_log.cron_job_update', 'admin_log.cron_job_update_desc'],
        'CronJobController@delete'    => ['admin_log.cron_job_delete', 'admin_log.cron_job_delete_desc'],
        'CronJobController@status'    => ['admin_log.cron_job_status', 'admin_log.cron_job_status_desc'],
        'CronJobController@clearLogs' => ['admin_log.cron_job_clear_logs', 'admin_log.cron_job_clear_logs_desc'],
        'CronJobController@run'       => ['admin_log.cron_job_run', 'admin_log.cron_job_run_desc'],

        // 在线管理员（M4）
        'OnlineController@logout' => ['admin_log.online_logout', 'admin_log.online_logout_desc'],

        // 会员管理（M5a）。三条写动作都要留审计痕迹：调余额、调积分改的是钱，改状态会让人立刻登不上
        'UserManageController@adjustBalance' => ['admin_log.user_adjust_balance', 'admin_log.user_adjust_balance_desc'],
        'UserManageController@adjustPoints'  => ['admin_log.user_adjust_points', 'admin_log.user_adjust_points_desc'],
        'UserManageController@updateStatus'  => ['admin_log.user_status', 'admin_log.user_status_desc'],

        // 消息模板（M6b）
        'MessageTemplateController@store'  => ['admin_log.message_template_create', 'admin_log.message_template_create_desc'],
        'MessageTemplateController@update' => ['admin_log.message_template_update', 'admin_log.message_template_update_desc'],
        'MessageTemplateController@delete' => ['admin_log.message_template_delete', 'admin_log.message_template_delete_desc'],

        // 公众号自动回复（M6c）
        'AutoReplyController@store'  => ['admin_log.auto_reply_create', 'admin_log.auto_reply_create_desc'],
        'AutoReplyController@update' => ['admin_log.auto_reply_update', 'admin_log.auto_reply_update_desc'],
        'AutoReplyController@delete' => ['admin_log.auto_reply_delete', 'admin_log.auto_reply_delete_desc'],
    ],

    // 不记操作日志的写接口（短类名@方法）：WS 握手票据——前端每次重连都会取一次，记下来只是刷屏，也不改变任何业务数据。
    // 与 actions 互斥；tests/Feature/System/AdminLogMiddlewareTest 会核对它们都对得上写路由。
    'skip' => [
        'WsTicketController@store',
    ],

    'masked_params' => [
        // PUT /system/config/{id} 只提交 config_value，看不出是哪个键，一律脱敏（云存储、支付密钥都走这里）
        'SystemConfigController@update' => ['config_value'],
    ],
];
