<?php
// Business logic errors - English

return [
    // Menu
    'menu_name_exists'          => 'Menu name already exists',
    'route_path_exists'         => 'Route path already exists',
    'parent_menu_not_found'     => 'Parent menu not found',
    'button_no_children'        => 'Button type menu cannot have children',
    'menu_not_found'            => 'Menu not found',
    'parent_not_self'           => 'Cannot set self as parent menu',
    'parent_not_child'          => 'Cannot set child menu as parent',
    'menu_has_children'         => 'Menu has children and cannot be deleted',
    'menu_used_by_role'         => 'Menu is used by roles and cannot be deleted',
    'sort_field_missing'        => 'Sort item missing required fields',
    'sort_field_type_error'     => 'Sort field type is incorrect',
    'sort_parent_mismatch'      => 'Menus do not belong to the same parent, sorting not allowed',

    // Role
    'role_code_exists'          => 'Role identifier already exists',
    'role_not_found'            => 'Role not found',
    'system_role_no_modify'     => 'System role identifier cannot be modified',
    'system_role_no_delete'     => 'System role cannot be deleted',
    'role_has_admins'           => 'Role has assigned admins and cannot be deleted',
    'system_role_no_status'     => 'System role status cannot be changed',

    // Department
    'dept_code_exists'          => 'Department code already exists',
    'parent_dept_not_found'     => 'Parent department not found',
    'dept_not_found'            => 'Department not found',
    'dept_parent_not_self'      => 'Parent department cannot be itself',
    'dept_parent_not_child'     => 'Parent department cannot be a child department',
    'dept_has_children'         => 'Department has sub-departments and cannot be deleted',
    'dept_has_admins'           => 'Department has admins and cannot be deleted',

    // Dictionary
    'dict_not_found'            => 'Dictionary not found',
    'dict_code_exists'          => 'Dictionary code already exists',
    'dict_item_not_found'       => 'Dictionary item not found',
    'dict_item_value_exists'    => 'Dictionary item value already exists',

    // Permission
    'permission_code_exists'    => 'Permission identifier already exists',
    'permission_not_found'      => 'Permission not found',
    'permission_used_by_role'   => 'Permission is used by roles and cannot be deleted',
    'no_permission_to_create'   => 'No permissions to create',

    // File
    'file_not_found'            => 'File not found',
    'please_select_file'        => 'Please select a file',
    'please_select_files'       => 'Please select files to delete',
    'file_name_required'        => 'File name is required',
    'please_input_group'        => 'Please enter group name',
    'upload_image_only'         => 'Only image files are allowed (jpg, png, gif, webp)',
    'file_size_2mb'             => 'File size cannot exceed 2MB',
    'file_size_10mb'            => 'File size cannot exceed 10MB',
    'upload_failed'             => 'File upload failed',
    'please_select_upload'      => 'Please select a file to upload',

    // Notification
    'notification_not_found'    => 'Notification not found',

    // Cron Job
    'task_not_found'            => 'Task not found',

    // Config
    'config_not_found'          => 'Configuration not found',
    'config_value_required'     => 'Configuration value is required',

    // Message Template
    'template_code_exists'      => 'Template code already exists',
    'template_not_found'        => 'Template not found',
    'template_disabled'         => 'Message template [%s] not found or disabled',
    'template_code_required'    => 'Template code is required',

    // Payment
    'order_no_required'         => 'Order number is required',
    'refund_amount_positive'    => 'Refund amount must be greater than 0',
    'payment_order_not_found'   => 'Payment order not found',
    'order_status_no_refund'    => 'Order status does not allow refund',
    'refund_exceed_amount'      => 'Refund amount cannot exceed order amount',

    // WeChat
    'miniapp_login_failed'      => 'Mini program login failed',
    'get_phone_failed'          => 'Failed to get phone number',
    'send_subscribe_failed'     => 'Failed to send subscription message',
    'generate_qrcode_failed'    => 'Failed to generate mini program QR code',
    'wechat_api_error'          => 'WeChat API error',
    'reply_exists_follow'       => 'An active follow reply already exists',
    'reply_exists_default'      => 'An active default reply already exists',
    'auto_reply_not_found'      => 'Auto reply not found',
    'menu_content_required'     => 'Menu content is required',
    'openid_template_required'  => 'openid and template_id are required',
    'openid_required'           => 'openid parameter is missing',

    // User
    'user_not_found'            => 'User not found',
    'user_account_disabled'     => 'Account has been disabled',
    'user_password_error'       => 'Incorrect password',
    'no_updatable_fields'       => 'No updatable fields',
    'user_old_password_error'   => 'Current password is incorrect',

    // Code Generator
    'please_specify_table'      => 'Please specify a database table',
    'please_fill_config'        => 'Please fill in the complete configuration',
    'record_not_found'          => 'Record not found',

    // SMS
    'aliyun_sms_config_incomplete'  => 'Aliyun SMS configuration is incomplete',
    'tencent_sms_config_incomplete' => 'Tencent Cloud SMS configuration is incomplete',
    'send_failed'                   => 'Send failed',

    // Upload
    'upload_failed_reason'      => 'Upload failed: %s',

    // Login
    'login_failed_reason'       => 'Login failed: %s',

    // Dashboard
    'get_stats_failed'          => 'Failed to get stats: %s',
    'get_login_log_failed'      => 'Failed to get login logs: %s',

    // Dictionary
    'dict_code_required'        => 'Dictionary code is required',

    // Menu
    'menu_sort_failed_log'      => 'Menu batch sort failed',

    // Selection prompts
    'please_select_admin'       => 'Please select admins to delete',
    'please_select_role'        => 'Please select roles to delete',
    'please_select_menu'        => 'Please select menus to delete',
    'please_select_dict'        => 'Please select dictionaries to delete',
    'sort_data_required'        => 'Sort data is required',
    'permission_data_required'  => 'Permission data is required',

    // Payment drivers
    'alipay_not_enabled'                    => 'Alipay is not enabled',
    'wechat_pay_not_enabled'                => 'WeChat Pay is not enabled',
    'alipay_config_incomplete'              => 'Alipay configuration is incomplete, please complete payment settings in system configuration',
    'alipay_create_order_failed'            => 'Alipay order creation failed',
    'alipay_query_order_failed'             => 'Alipay order query failed',
    'alipay_refund_failed'                  => 'Alipay refund failed',
    'alipay_callback_verify_failed'         => 'Alipay callback signature verification failed',
    'alipay_callback_process_failed'        => 'Alipay callback processing failed',
    'wechat_pay_config_incomplete'          => 'WeChat Pay configuration is incomplete, please complete payment settings in system configuration',
    'wechat_pay_key_not_found'              => 'WeChat Pay private key file not found',
    'wechat_pay_create_order_failed'        => 'WeChat Pay order creation failed',
    'wechat_pay_query_order_failed'         => 'WeChat Pay order query failed',
    'wechat_pay_refund_failed'              => 'WeChat Pay refund failed',
    'callback_data_empty'                   => 'Callback data is empty',
    'callback_data_decrypt_error'           => 'Callback data format error after decryption',
    'wechat_pay_callback_verify_failed'     => 'WeChat Pay callback verification failed',
    'wechat_callback_missing_signature'     => 'WeChat callback missing signature headers',
    'wechat_callback_timestamp_expired'     => 'WeChat callback timestamp has expired',
    'wechat_callback_sign_failed'           => 'WeChat callback signature verification failed',

    // Image processing
    'image_open_failed'         => 'Failed to open image',
    'image_save_failed'         => 'Failed to save image',
    'thumbnail_failed'          => 'Thumbnail generation failed',
    'image_compress_failed'     => 'Image compression failed',

    // Storage drivers
    'tencent_cos_config_incomplete' => 'Tencent Cloud COS configuration is incomplete, please complete storage settings in system configuration',
    'tencent_cos_upload_failed'     => 'Tencent Cloud COS upload failed',
    'tencent_cos_delete_failed'     => 'Tencent Cloud COS delete failed',
    'invalid_file'                  => 'Invalid file',
    'file_type_not_allowed'         => 'File type not allowed',
    'file_size_exceeded'            => 'File size exceeds the limit',
    'aliyun_oss_config_incomplete'  => 'Aliyun OSS configuration is incomplete, please complete storage settings in system configuration',
    'aliyun_oss_upload_failed'      => 'Aliyun OSS upload failed',
    'aliyun_oss_delete_failed'      => 'Aliyun OSS delete failed',
    'aliyun_oss_url_failed'         => 'Failed to get OSS file URL',
    'qiniu_config_incomplete'       => 'Qiniu Cloud configuration is incomplete, please complete storage settings in system configuration',
    'qiniu_upload_failed'           => 'Qiniu Cloud upload failed',
    'qiniu_delete_failed'           => 'Qiniu Cloud delete failed',
    'local_read_failed'             => 'Failed to read local file',
    'local_write_failed'            => 'Local storage write failed',

    // WeChat manager
    'wechat_official_config_incomplete' => 'WeChat Official Account configuration is incomplete, please complete WeChat settings in system configuration',
    'wechat_miniapp_config_incomplete'  => 'WeChat Mini Program configuration is incomplete, please complete WeChat settings in system configuration',

    // API
    'invalid_mobile_format'         => 'Please enter a valid phone number',
    'sms_rate_limit'                => 'Sending too frequently, please try again later',
    'mobile_password_required'      => 'Phone number and password are required',
    'mobile_code_required'          => 'Phone number and verification code are required',
    'register_fields_required'      => 'Mobile, password and verification code are required',
    'mobile_already_registered'     => 'This mobile number is already registered',
    'missing_code_param'        => 'Missing code parameter',
    'password_min_length'       => 'New password must be at least 6 characters',
    'select_payment_channel'    => 'Please select a payment channel',
    'invalid_payment_amount'    => 'Invalid payment amount',
    'order_title_required'      => 'Order title is required',
    'params_incomplete'         => 'Parameters incomplete',
    'user_refund_reason'        => 'User requested refund',
    'missing_redirect_url'      => 'Missing redirect_url parameter',
    'missing_code'              => 'Missing code parameter',
];
