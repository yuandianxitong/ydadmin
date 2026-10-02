<?php

// Business rule errors (BusinessException: HTTP 200 + code 400)
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
    'sort_data_required'        => 'Sort data is required',
    'please_select_menu'        => 'Please select menus to delete',

    // Role
    'role_code_exists'          => 'Role identifier already exists',
    'role_not_found'            => 'Role not found',
    'system_role_no_modify'     => 'System role identifier cannot be modified',
    'system_role_no_delete'     => 'System role cannot be deleted',
    'role_has_admins'           => 'Role has assigned admins and cannot be deleted',
    'system_role_no_status'     => 'System role status cannot be changed',
    'system_role_no_permission' => 'System role permissions cannot be modified',
    'system_role_no_assign'     => 'Only a super admin can assign system roles',
    'please_select_role'        => 'Please select roles to delete',

    // Department
    'dept_code_exists'          => 'Department code already exists',
    'parent_dept_not_found'     => 'Parent department not found',
    'dept_not_found'            => 'Department not found',
    'dept_parent_not_self'      => 'Parent department cannot be itself',
    'dept_parent_not_child'     => 'Parent department cannot be a child department',
    'dept_has_children'         => 'Department has sub-departments and cannot be deleted',
    'dept_has_admins'           => 'Department has admins and cannot be deleted',

    // Admin
    'please_select_admin'          => 'Please select admins to delete',
    'dept_out_of_scope'            => 'The department is outside your data scope',
    'cannot_change_own_department' => 'You cannot change your own department',
    'cannot_change_own_roles'      => 'You cannot change your own roles',
    'role_exceeds_own_permissions' => 'Cannot assign roles with permissions beyond your own',
    'role_scope_exceeds_own'       => 'Cannot assign roles whose data scope exceeds your own',

    // Dictionary
    'dict_not_found'         => 'Dictionary not found',
    'dict_code_exists'       => 'Dictionary code already exists',
    'dict_item_not_found'    => 'Dictionary item not found',
    'dict_item_value_exists' => 'Dictionary item value already exists',

    // Notification
    'notification_not_found' => 'Notification not found',

    // System config
    'config_not_found'          => 'Configuration not found',
    'config_key_not_found'      => 'Configuration key not found: :key',
    'config_value_invalid'      => 'Invalid value for configuration :key',
    'upload_size_exceeds_package_limit' => ':key cannot exceed :max MB (bounded by the server request size limit)',

    // File & upload
    'please_select_upload'      => 'Please select a file to upload',
    'upload_image_only'         => 'Only image files can be uploaded',
    'file_type_not_allowed'     => 'This file type is not allowed',
    'image_size_exceeded'       => 'Image size may not exceed :size MB',
    'file_size_exceeded'        => 'File size may not exceed :size MB',
    'file_not_found'            => 'File not found',

    // Storage
    'storage_driver_unsupported'  => 'Unsupported storage driver: :driver',
    'storage_config_incomplete'   => 'Cloud storage is not fully configured. Complete the storage settings before uploading',
    'storage_oss_region_required' => 'Please configure the OSS region, or use a standard endpoint so it can be derived',
    'storage_upload_failed'       => 'Failed to upload the file to :driver: :error',
    'storage_delete_failed'       => 'Failed to delete the file on :driver: :error',

    // Scheduled jobs (M3)
    'cron_command_not_allowed'    => 'The command is not in the scheduled job whitelist',

    // Scheduled task execution
    'cron_job_running'            => 'The task is already running, please try again later',
    'cron_run_submitted'          => 'Submitted for execution; check the run logs for the result',
    'cron_run_dispatch_failed'    => 'Failed to submit for execution; check that the queue service (Redis) is available',

    // WebSocket realtime channel
    'ws_ticket_rate_limited'      => 'Too many realtime connection requests, please try again later',

    // Realtime channel (M4)
    'realtime_force_logout'       => 'You have been signed out by an administrator. Please log in again',

    // Member (M5a)
    'mobile_registered'           => 'This mobile number is already registered',
    'register_closed'             => 'Registration is currently closed',

    // Member assets (M5a)
    'balance_log_type_1' => 'Recharge',
    'balance_log_type_2' => 'Consumption',
    'balance_log_type_3' => 'Refund',
    'balance_log_type_4' => 'Admin adjustment',
    'points_log_type_1'  => 'Admin adjustment',
    'points_log_type_2'  => 'Registration bonus',
    'points_log_type_3'  => 'Daily check-in',
    'points_log_type_4'  => 'Consumption bonus',
    'points_log_type_5'  => 'Consumption deduction',

    // SMS (M5a)
    'sms_driver_unsupported'        => 'Unsupported SMS provider: :driver',
    'sms_config_incomplete_aliyun'  => 'Aliyun SMS is not fully configured. Fill in the AccessKey, AccessSecret and signature under System Settings - SMS',
    'sms_config_incomplete_tencent' => 'Tencent Cloud SMS is not fully configured. Fill in the SecretId, SecretKey, signature and SDK app id under System Settings - SMS',
    'sms_tencent_sdk_missing'       => 'The Tencent Cloud SMS SDK is not installed. Run composer require tencentcloud/sms first',
    'sms_template_missing'          => 'The SMS template is not configured. Fill in the template id for this scene under System Settings - SMS',
    'sms_send_failed'               => 'Failed to send the SMS, please try again later',

    // SMS verification code (M5a)
    'sms_mobile_not_registered'     => 'This mobile number is not registered yet',
    'sms_mobile_registered'         => 'This mobile number is already registered',
    'sms_rate_limited_minute'       => 'Verification codes are being requested too often, please retry in :seconds seconds',
    'sms_rate_limited_day'          => 'Daily verification code limit reached (:limit), please try again tomorrow',
    'sms_rate_limited_ip'           => 'Too many verification code requests from this network (max :limit per hour), please try again later',
    'sms_verify_too_many_attempts'  => 'Too many incorrect attempts. This verification code is no longer valid, please request a new one',
];
