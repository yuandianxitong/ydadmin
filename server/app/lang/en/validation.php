<?php
// Validation messages - English

return [
    // Login
    'username_require'          => 'Username is required',
    'username_length_3_50'      => 'Username must be 3-50 characters',
    'username_length_3_20'      => 'Username must be 3-20 characters',
    'username_alpha_dash'       => 'Username can only contain letters, numbers, underscores and dashes',
    'username_unique'           => 'Username already exists',
    'password_require'          => 'Password is required',
    'password_length'           => 'Password must be 6-20 characters',
    'captcha_require'           => 'Please enter the captcha',
    'captcha_length'            => 'Invalid captcha length',
    'captcha_expired'           => 'Captcha has expired, please refresh',

    // Email/Mobile
    'email_require'             => 'Email is required',
    'email_format'              => 'Invalid email format',
    'email_unique'              => 'Email already exists',
    'mobile_format'             => 'Invalid mobile number format',

    // Admin
    'nickname_length'           => 'Nickname must be 2-20 characters',
    'avatar_url'                => 'Avatar must be a valid URL',
    'department_max'            => 'Department cannot exceed 100 characters',
    'position_max'              => 'Position cannot exceed 100 characters',
    'status_invalid'            => 'Invalid status value',
    'role_ids_array'            => 'Roles must be an array',
    'role_ids_integer'          => 'Role ID must be an integer',

    // Role
    'role_name_require'         => 'Role identifier is required',
    'role_name_length'          => 'Role identifier must be 2-50 characters',
    'role_name_alpha_dash'      => 'Role identifier can only contain letters, numbers, underscores and dashes',
    'role_name_unique'          => 'Role identifier already exists',
    'role_title_require'        => 'Role name is required',
    'role_title_length'         => 'Role name must be 2-100 characters',
    'role_desc_max'             => 'Role description cannot exceed 500 characters',
    'data_scope_invalid'        => 'Invalid data scope value',
    'permission_ids_array'      => 'Permissions must be an array',
    'permission_ids_integer'    => 'Permission ID must be an integer',
    'menu_ids_array'            => 'Menus must be an array',
    'menu_ids_integer'          => 'Menu ID must be an integer',

    // Menu
    'parent_id_integer'         => 'Parent ID must be an integer',
    'parent_id_min'             => 'Parent ID cannot be less than 0',
    'menu_type_require'         => 'Menu type is required',
    'menu_type_invalid'         => 'Invalid menu type',
    'menu_title_require'        => 'Menu title is required',
    'menu_title_length'         => 'Menu title must be 1-100 characters',
    'route_name_length'         => 'Route name must be 1-100 characters',
    'route_path_length'         => 'Route path must be 1-200 characters',
    'component_length'          => 'Component path must be 1-255 characters',
    'redirect_length'           => 'Redirect path must be 1-200 characters',
    'icon_length'               => 'Icon must be 1-100 characters',
    'permission_length'         => 'Permission identifier must be 1-100 characters',
    'is_hidden_boolean'         => 'Hidden must be a boolean',
    'is_cache_boolean'          => 'Cache must be a boolean',
    'is_affix_boolean'          => 'Affix must be a boolean',
    'is_iframe_boolean'         => 'Iframe must be a boolean',
    'external_link_url'         => 'External link must be a valid URL',
    'breadcrumb_boolean'        => 'Breadcrumb must be a boolean',
    'active_menu_length'        => 'Active menu must be 1-200 characters',
    'sort_integer'              => 'Sort order must be an integer',
    'sort_min'                  => 'Sort order cannot be less than 0',
    'menu_name_require'         => 'Route name is required for menu type',
    'menu_path_require'         => 'Route path is required for menu type',
    'menu_component_require'    => 'Component path is required for menu type',
    'button_permission_require' => 'Permission identifier is required for button type',

    // Permission
    'perm_name_require'         => 'Permission identifier is required',
    'perm_name_length'          => 'Permission identifier must be 2-100 characters',
    'perm_name_unique'          => 'Permission identifier already exists',
    'perm_title_require'        => 'Permission name is required',
    'perm_title_length'         => 'Permission name must be 2-100 characters',
    'perm_group_require'        => 'Permission group is required',
    'perm_group_length'         => 'Permission group must be 2-50 characters',
    'perm_desc_max'             => 'Permission description cannot exceed 500 characters',
    'guard_name_length'         => 'Guard name must be 2-50 characters',

    // Department
    'parent_id_require'         => 'Please select parent department',
    'dept_name_require'         => 'Please enter department name',
    'dept_name_max'             => 'Department name cannot exceed 100 characters',
    'dept_code_max'             => 'Department code cannot exceed 50 characters',

    // Dictionary
    'dict_name_require'         => 'Dictionary name is required',
    'dict_name_length'          => 'Dictionary name must be 1-100 characters',
    'dict_code_require'         => 'Dictionary code is required',
    'dict_code_length'          => 'Dictionary code must be 1-100 characters',
    'dict_code_alpha_dash'      => 'Dictionary code can only contain letters, numbers, underscores and dashes',
    'description_max'           => 'Description cannot exceed 500 characters',

    // Dictionary Item
    'dict_id_require'           => 'Dictionary ID is required',
    'dict_id_integer'           => 'Dictionary ID must be an integer',
    'label_require'             => 'Label is required',
    'label_length'              => 'Label must be 1-100 characters',
    'value_require'             => 'Value is required',
    'value_length'              => 'Value must be 1-100 characters',
    'tag_type_max'              => 'Tag type cannot exceed 50 characters',

    // Config
    'config_value_require'      => 'Configuration value is required',
    'configs_require'           => 'Configuration data is required',
    'configs_array'             => 'Configuration data format is invalid',

    // Cron Job
    'task_name_require'         => 'Please enter task name',
    'task_name_max'             => 'Task name cannot exceed 100 characters',
    'command_require'           => 'Please enter command',
    'command_max'               => 'Command cannot exceed 255 characters',
    'expression_require'        => 'Please enter cron expression',
    'expression_max'            => 'Cron expression cannot exceed 100 characters',

    // Notification
    'notification_title_require' => 'Please enter notification title',
    'notification_title_max'    => 'Title cannot exceed 200 characters',
    'notification_content_require' => 'Please enter notification content',
    'notification_type_require' => 'Please select notification type',
    'notification_type_invalid' => 'Invalid notification type',

    // Common
    'name_require'              => 'Name is required',
    'title_require'             => 'Title is required',
    'status_require'            => 'Status is required',
    'id_require'                => 'ID is required',
    'id_integer'                => 'ID must be an integer',
    'id_gt_zero'                => 'ID must be greater than 0',
    'page_integer'              => 'Page number must be an integer',
    'page_gt_zero'              => 'Page number must be greater than 0',
    'limit_integer'             => 'Items per page must be an integer',
    'limit_between'             => 'Items per page must be between 1-100',
    'status_integer'            => 'Status must be an integer',
    'remark_max'                => 'Remark cannot exceed 255 characters',
];
