<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => 'The :attribute field must be accepted.',
    'accepted_if' => 'The :attribute field must be accepted when :other is :value.',
    'active_url' => 'The :attribute field must be a valid URL.',
    'after' => 'The :attribute field must be a date after :date.',
    'after_or_equal' => 'The :attribute field must be a date after or equal to :date.',
    'alpha' => 'The :attribute field must only contain letters.',
    'alpha_dash' => 'The :attribute field must only contain letters, numbers, dashes, and underscores.',
    'alpha_num' => 'The :attribute field must only contain letters and numbers.',
    'any_of' => 'The :attribute field is invalid.',
    'array' => 'The :attribute field must be an array.',
    'array_keys' => 'The :attribute field must only contain the following keys: :values.',
    'ascii' => 'The :attribute field must only contain single-byte alphanumeric characters and symbols.',
    'base64' => 'The :attribute field must be a valid Base64 string.',
    'before' => 'The :attribute field must be a date before :date.',
    'before_or_equal' => 'The :attribute field must be a date before or equal to :date.',
    'between' => [
        'array' => 'The :attribute field must have between :min and :max items.',
        'file' => 'The :attribute field must be between :min and :max kilobytes.',
        'numeric' => 'The :attribute field must be between :min and :max.',
        'string' => 'The :attribute field must be between :min and :max characters.',
    ],
    'boolean' => 'The :attribute field must be true or false.',
    'can' => 'The :attribute field contains an unauthorized value.',
    'confirmed' => 'The :attribute field confirmation does not match.',
    'contains' => 'The :attribute field is missing a required value.',
    'current_password' => 'The password is incorrect.',
    'date' => 'The :attribute field must be a valid date.',
    'date_equals' => 'The :attribute field must be a date equal to :date.',
    'date_format' => 'The :attribute field must match the format :format.',
    'decimal' => 'The :attribute field must have :decimal decimal places.',
    'declined' => 'The :attribute field must be declined.',
    'declined_if' => 'The :attribute field must be declined when :other is :value.',
    'different' => 'The :attribute field and :other must be different.',
    'digits' => 'The :attribute field must be :digits digits.',
    'digits_between' => 'The :attribute field must be between :min and :max digits.',
    'dimensions' => 'The :attribute field has invalid image dimensions.',
    'distinct' => 'The :attribute field has a duplicate value.',
    'doesnt_contain' => 'The :attribute field must not contain any of the following: :values.',
    'doesnt_end_with' => 'The :attribute field must not end with one of the following: :values.',
    'doesnt_start_with' => 'The :attribute field must not start with one of the following: :values.',
    'email' => 'The :attribute field must be a valid email address.',
    'encoding' => 'The :attribute field must be encoded in :encoding.',
    'ends_with' => 'The :attribute field must end with one of the following: :values.',
    'enum' => 'The selected :attribute is invalid.',
    'exists' => 'The selected :attribute is invalid.',
    'extensions' => 'The :attribute field must have one of the following extensions: :values.',
    'file' => 'The :attribute field must be a file.',
    'filled' => 'The :attribute field must have a value.',
    'gt' => [
        'array' => 'The :attribute field must have more than :value items.',
        'file' => 'The :attribute field must be greater than :value kilobytes.',
        'numeric' => 'The :attribute field must be greater than :value.',
        'string' => 'The :attribute field must be greater than :value characters.',
    ],
    'gte' => [
        'array' => 'The :attribute field must have :value items or more.',
        'file' => 'The :attribute field must be greater than or equal to :value kilobytes.',
        'numeric' => 'The :attribute field must be greater than or equal to :value.',
        'string' => 'The :attribute field must be greater than or equal to :value characters.',
    ],
    'hex_color' => 'The :attribute field must be a valid hexadecimal color.',
    'image' => 'The :attribute field must be an image.',
    'in' => 'The selected :attribute is invalid.',
    'in_array' => 'The :attribute field must exist in :other.',
    'in_array_keys' => 'The :attribute field must contain at least one of the following keys: :values.',
    'integer' => 'The :attribute field must be an integer.',
    'ip' => 'The :attribute field must be a valid IP address.',
    'ipv4' => 'The :attribute field must be a valid IPv4 address.',
    'ipv6' => 'The :attribute field must be a valid IPv6 address.',
    'json' => 'The :attribute field must be a valid JSON string.',
    'list' => 'The :attribute field must be a list.',
    'lowercase' => 'The :attribute field must be lowercase.',
    'lt' => [
        'array' => 'The :attribute field must have less than :value items.',
        'file' => 'The :attribute field must be less than :value kilobytes.',
        'numeric' => 'The :attribute field must be less than :value.',
        'string' => 'The :attribute field must be less than :value characters.',
    ],
    'lte' => [
        'array' => 'The :attribute field must not have more than :value items.',
        'file' => 'The :attribute field must be less than or equal to :value kilobytes.',
        'numeric' => 'The :attribute field must be less than or equal to :value.',
        'string' => 'The :attribute field must be less than or equal to :value characters.',
    ],
    'mac_address' => 'The :attribute field must be a valid MAC address.',
    'max' => [
        'array' => 'The :attribute field must not have more than :max items.',
        'file' => 'The :attribute field must not be greater than :max kilobytes.',
        'numeric' => 'The :attribute field must not be greater than :max.',
        'string' => 'The :attribute field must not be greater than :max characters.',
    ],
    'max_digits' => 'The :attribute field must not have more than :max digits.',
    'mimes' => 'The :attribute field must be a file of type: :values.',
    'mimetypes' => 'The :attribute field must be a file of type: :values.',
    'min' => [
        'array' => 'The :attribute field must have at least :min items.',
        'file' => 'The :attribute field must be at least :min kilobytes.',
        'numeric' => 'The :attribute field must be at least :min.',
        'string' => 'The :attribute field must be at least :min characters.',
    ],
    'min_digits' => 'The :attribute field must have at least :min digits.',
    'missing' => 'The :attribute field must be missing.',
    'missing_if' => 'The :attribute field must be missing when :other is :value.',
    'missing_unless' => 'The :attribute field must be missing unless :other is :value.',
    'missing_with' => 'The :attribute field must be missing when :values is present.',
    'missing_with_all' => 'The :attribute field must be missing when :values are present.',
    'multiple_of' => 'The :attribute field must be a multiple of :value.',
    'not_in' => 'The selected :attribute is invalid.',
    'not_regex' => 'The :attribute field format is invalid.',
    'numeric' => 'The :attribute field must be a number.',
    'password' => [
        'letters' => 'The :attribute field must contain at least one letter.',
        'mixed' => 'The :attribute field must contain at least one uppercase and one lowercase letter.',
        'numbers' => 'The :attribute field must contain at least one number.',
        'symbols' => 'The :attribute field must contain at least one symbol.',
        'uncompromised' => 'The given :attribute has appeared in a data leak. Please choose a different :attribute.',
    ],
    'present' => 'The :attribute field must be present.',
    'present_if' => 'The :attribute field must be present when :other is :value.',
    'present_unless' => 'The :attribute field must be present unless :other is :value.',
    'present_with' => 'The :attribute field must be present when :values is present.',
    'present_with_all' => 'The :attribute field must be present when :values are present.',
    'prohibited' => 'The :attribute field is prohibited.',
    'prohibited_if' => 'The :attribute field is prohibited when :other is :value.',
    'prohibited_if_accepted' => 'The :attribute field is prohibited when :other is accepted.',
    'prohibited_if_declined' => 'The :attribute field is prohibited when :other is declined.',
    'prohibited_unless' => 'The :attribute field is prohibited unless :other is in :values.',
    'prohibits' => 'The :attribute field prohibits :other from being present.',
    'regex' => 'The :attribute field format is invalid.',
    'required' => 'The :attribute field is required.',
    'required_array_keys' => 'The :attribute field must contain entries for: :values.',
    'required_if' => 'The :attribute field is required when :other is :value.',
    'required_if_accepted' => 'The :attribute field is required when :other is accepted.',
    'required_if_declined' => 'The :attribute field is required when :other is declined.',
    'required_unless' => 'The :attribute field is required unless :other is in :values.',
    'required_with' => 'The :attribute field is required when :values is present.',
    'required_with_all' => 'The :attribute field is required when :values are present.',
    'required_without' => 'The :attribute field is required when :values is not present.',
    'required_without_all' => 'The :attribute field is required when none of :values are present.',
    'same' => 'The :attribute field must match :other.',
    'size' => [
        'array' => 'The :attribute field must contain :size items.',
        'file' => 'The :attribute field must be :size kilobytes.',
        'numeric' => 'The :attribute field must be :size.',
        'string' => 'The :attribute field must be :size characters.',
    ],
    'starts_with' => 'The :attribute field must start with one of the following: :values.',
    'string' => 'The :attribute field must be a string.',
    'timezone' => 'The :attribute field must be a valid timezone.',
    'unique' => 'The :attribute has already been taken.',
    'uploaded' => 'The :attribute failed to upload.',
    'uppercase' => 'The :attribute field must be uppercase.',
    'url' => 'The :attribute field must be a valid URL.',
    'ulid' => 'The :attribute field must be a valid ULID.',
    'uuid' => 'The :attribute field must be a valid UUID.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [],

    /*
    |--------------------------------------------------------------------------
    | App Custom Validation Message Keys
    |--------------------------------------------------------------------------
    |
    | Application-level message keys referenced by ValidatorFactory's
    | `messages` array (e.g. 'username.required' => 'validation.username_require').
    | Mirrors resource/lang/zh_CN/validation.php's custom key set — see that
    | file's header comment for the split rationale.
    |
    */

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

    // Message Template
    'template_name_require'     => 'Please enter template name',
    'template_name_max'         => 'Template name cannot exceed 100 characters',
    'template_code_require'     => 'Please enter template code',
    'template_code_max'         => 'Template code cannot exceed 50 characters',

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

    // User feedback (M2.3c-Task4: missing in the legacy repo itself, added here, see §6/§9)
    'reply_require'             => 'Reply content is required',
    'reply_length'              => 'Reply content cannot exceed 1000 characters',

    // Wechat auto reply (M2.4d-Task4: legacy repo lacks minimal required-field validation, added here as a proactive hardening point, see reference §6 point 5)
    'auto_reply_type_require'       => 'Reply type is required',
    'auto_reply_type_invalid'       => 'Reply type is invalid',
    'auto_reply_keyword_max'        => 'Keyword cannot exceed 200 characters',
    'auto_reply_match_type_invalid' => 'Match type is invalid',
    'auto_reply_content_require'    => 'Reply content is required',
];
