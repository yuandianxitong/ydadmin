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

        // 公众号自定义菜单（M6c）
        'OfficialAccountController@createMenu' => ['admin_log.official_menu_create', 'admin_log.official_menu_create_desc'],
        'OfficialAccountController@deleteMenu' => ['admin_log.official_menu_delete', 'admin_log.official_menu_delete_desc'],

        // 文章栏目（M7a）
        'ArticleCategoryController@store'  => ['admin_log.article_category_create', 'admin_log.article_category_create_desc'],
        'ArticleCategoryController@update' => ['admin_log.article_category_update', 'admin_log.article_category_update_desc'],
        'ArticleCategoryController@delete' => ['admin_log.article_category_delete', 'admin_log.article_category_delete_desc'],
        'ArticleCategoryController@status' => ['admin_log.article_category_status', 'admin_log.article_category_status_desc'],

        // 文章（M7a）
        'ArticleController@store'  => ['admin_log.article_create', 'admin_log.article_create_desc'],
        'ArticleController@update' => ['admin_log.article_update', 'admin_log.article_update_desc'],
        'ArticleController@delete' => ['admin_log.article_delete', 'admin_log.article_delete_desc'],
        'ArticleController@status' => ['admin_log.article_status', 'admin_log.article_status_desc'],

        // 公告（M7a）
        'AnnouncementController@store'  => ['admin_log.announcement_create', 'admin_log.announcement_create_desc'],
        'AnnouncementController@update' => ['admin_log.announcement_update', 'admin_log.announcement_update_desc'],
        'AnnouncementController@delete' => ['admin_log.announcement_delete', 'admin_log.announcement_delete_desc'],
        'AnnouncementController@status' => ['admin_log.announcement_status', 'admin_log.announcement_status_desc'],

        // 协议（M7a）。无独立 status 动作，启用/禁用走 update。
        'AgreementController@store'  => ['admin_log.agreement_create', 'admin_log.agreement_create_desc'],
        'AgreementController@update' => ['admin_log.agreement_update', 'admin_log.agreement_update_desc'],
        'AgreementController@delete' => ['admin_log.agreement_delete', 'admin_log.agreement_delete_desc'],

        // 反馈（M7a）。无管理端创建动作，只记回复 / 关闭 / 删除。
        'FeedbackController@reply'  => ['admin_log.feedback_reply', 'admin_log.feedback_reply_desc'],
        'FeedbackController@close'  => ['admin_log.feedback_close', 'admin_log.feedback_close_desc'],
        'FeedbackController@delete' => ['admin_log.feedback_delete', 'admin_log.feedback_delete_desc'],

        // 地区（M7b）。无独立 status / batchDelete 路由。
        'RegionController@store'  => ['admin_log.region_create', 'admin_log.region_create_desc'],
        'RegionController@update' => ['admin_log.region_update', 'admin_log.region_update_desc'],
        'RegionController@delete' => ['admin_log.region_delete', 'admin_log.region_delete_desc'],

        // 应用版本（M7b）。无独立 status / batchDelete 路由。
        'AppVersionController@store'  => ['admin_log.version_create', 'admin_log.version_create_desc'],
        'AppVersionController@update' => ['admin_log.version_update', 'admin_log.version_update_desc'],
        'AppVersionController@delete' => ['admin_log.version_delete', 'admin_log.version_delete_desc'],

        // 数据导入（M7b）。history 是读，不登记。
        'DataImportController@upload' => ['admin_log.dataimport_upload', 'admin_log.dataimport_upload_desc'],

        // 装修页面（M7c）。读接口不登记；widget-preview 是 POST 但不写库，进 skip。
        'DiyPageController@saveHome'            => ['admin_log.diy_home_save', 'admin_log.diy_home_save_desc'],
        'DiyPageController@publishHome'         => ['admin_log.diy_home_publish', 'admin_log.diy_home_publish_desc'],
        'DiyPageController@restoreVersion'      => ['admin_log.diy_home_restore', 'admin_log.diy_home_restore_desc'],
        'DiyPageController@saveDraftByKey'      => ['admin_log.diy_page_save', 'admin_log.diy_page_save_desc'],
        'DiyPageController@publishByKey'        => ['admin_log.diy_page_publish', 'admin_log.diy_page_publish_desc'],
        'DiyPageController@restoreVersionByKey' => ['admin_log.diy_page_restore', 'admin_log.diy_page_restore_desc'],
        'DiyPageController@createPage'          => ['admin_log.diy_page_create', 'admin_log.diy_page_create_desc'],
        'DiyPageController@copyPage'            => ['admin_log.diy_page_copy', 'admin_log.diy_page_copy_desc'],
        'DiyPageController@updatePage'          => ['admin_log.diy_page_update', 'admin_log.diy_page_update_desc'],
        'DiyPageController@deletePage'          => ['admin_log.diy_page_delete', 'admin_log.diy_page_delete_desc'],
        'DiyLinkController@store'               => ['admin_log.diy_link_create', 'admin_log.diy_link_create_desc'],
        'DiyLinkController@update'              => ['admin_log.diy_link_update', 'admin_log.diy_link_update_desc'],
        'DiyLinkController@delete'              => ['admin_log.diy_link_delete', 'admin_log.diy_link_delete_desc'],

        // 移动端配置（M7c）。仅写接口登记。
        'MobileConfigController@update' => ['admin_log.mobile_config_update', 'admin_log.mobile_config_update_desc'],
    ],

    // 不记操作日志的写接口（短类名@方法）：WS 握手票据——前端每次重连都会取一次，记下来只是刷屏，也不改变任何业务数据。
    // 与 actions 互斥；tests/Feature/System/AdminLogMiddlewareTest 会核对它们都对得上写路由。
    'skip' => [
        'WsTicketController@store',
        'DiyPageController@previewWidget',
    ],

    'masked_params' => [
        // PUT /system/config/{id} 只提交 config_value，看不出是哪个键，一律脱敏（云存储、支付密钥都走这里）
        'SystemConfigController@update' => ['config_value'],
    ],
];
