<?php
// 验证消息 - 中文

return [
    // 登录
    'username_require'          => '用户名不能为空',
    'username_length_3_50'      => '用户名长度为3-50个字符',
    'username_length_3_20'      => '用户名长度为3-20个字符',
    'username_alpha_dash'       => '用户名只能包含字母、数字、下划线和破折号',
    'username_unique'           => '用户名已存在',
    'password_require'          => '密码不能为空',
    'password_length'           => '密码长度为6-20个字符',
    'captcha_require'           => '请输入验证码',
    'captcha_length'            => '验证码长度不正确',
    'captcha_expired'           => '验证码已失效，请刷新',

    // 邮箱/手机
    'email_require'             => '邮箱不能为空',
    'email_format'              => '邮箱格式不正确',
    'email_unique'              => '邮箱已存在',
    'mobile_format'             => '手机号格式不正确',

    // 管理员
    'nickname_length'           => '昵称长度为2-20个字符',
    'avatar_url'                => '头像必须是有效的URL',
    'department_max'            => '部门不能超过100个字符',
    'position_max'              => '职位不能超过100个字符',
    'status_invalid'            => '状态值无效',
    'role_ids_array'            => '角色必须是数组',
    'role_ids_integer'          => '角色ID必须是整数',

    // 角色
    'role_name_require'         => '角色标识不能为空',
    'role_name_length'          => '角色标识长度为2-50个字符',
    'role_name_alpha_dash'      => '角色标识只能包含字母、数字、下划线和破折号',
    'role_name_unique'          => '角色标识已存在',
    'role_title_require'        => '角色名称不能为空',
    'role_title_length'         => '角色名称长度为2-100个字符',
    'role_desc_max'             => '角色描述不能超过500个字符',
    'data_scope_invalid'        => '数据权限值无效',
    'permission_ids_array'      => '权限必须是数组',
    'permission_ids_integer'    => '权限ID必须是整数',
    'menu_ids_array'            => '菜单必须是数组',
    'menu_ids_integer'          => '菜单ID必须是整数',

    // 菜单
    'parent_id_integer'         => '父级ID必须是整数',
    'parent_id_min'             => '父级ID不能小于0',
    'menu_type_require'         => '菜单类型不能为空',
    'menu_type_invalid'         => '菜单类型值无效',
    'menu_title_require'        => '菜单标题不能为空',
    'menu_title_length'         => '菜单标题长度为1-100个字符',
    'route_name_length'         => '路由名称长度为1-100个字符',
    'route_path_length'         => '路由地址长度为1-200个字符',
    'component_length'          => '组件地址长度为1-255个字符',
    'redirect_length'           => '重定向地址长度为1-200个字符',
    'icon_length'               => '菜单图标长度为1-100个字符',
    'permission_length'         => '权限标识长度为1-100个字符',
    'is_hidden_boolean'         => '是否隐藏必须是布尔值',
    'is_cache_boolean'          => '是否缓存必须是布尔值',
    'is_affix_boolean'          => '是否固定必须是布尔值',
    'is_iframe_boolean'         => '是否内嵌必须是布尔值',
    'external_link_url'         => '外链地址必须是有效的URL',
    'breadcrumb_boolean'        => '面包屑必须是布尔值',
    'active_menu_length'        => '高亮菜单长度为1-200个字符',
    'sort_integer'              => '排序必须是整数',
    'sort_min'                  => '排序不能小于0',
    'menu_name_require'         => '菜单类型时路由名称不能为空',
    'menu_path_require'         => '菜单类型时路由地址不能为空',
    'menu_component_require'    => '菜单类型时组件地址不能为空',
    'button_permission_require' => '按钮类型时权限标识不能为空',

    // 权限
    'perm_name_require'         => '权限标识不能为空',
    'perm_name_length'          => '权限标识长度为2-100个字符',
    'perm_name_unique'          => '权限标识已存在',
    'perm_title_require'        => '权限名称不能为空',
    'perm_title_length'         => '权限名称长度为2-100个字符',
    'perm_group_require'        => '权限分组不能为空',
    'perm_group_length'         => '权限分组长度为2-50个字符',
    'perm_desc_max'             => '权限描述不能超过500个字符',
    'guard_name_length'         => '守卫名称长度为2-50个字符',

    // 部门
    'parent_id_require'         => '请选择上级部门',
    'dept_name_require'         => '请输入部门名称',
    'dept_name_max'             => '部门名称最多100个字符',
    'dept_code_max'             => '部门编码最多50个字符',

    // 字典
    'dict_name_require'         => '字典名称不能为空',
    'dict_name_length'          => '字典名称长度为1-100个字符',
    'dict_code_require'         => '字典编码不能为空',
    'dict_code_length'          => '字典编码长度为1-100个字符',
    'dict_code_alpha_dash'      => '字典编码只能包含字母、数字、下划线和破折号',
    'description_max'           => '描述不能超过500个字符',

    // 字典项
    'dict_id_require'           => '字典ID不能为空',
    'dict_id_integer'           => '字典ID必须是整数',
    'label_require'             => '标签不能为空',
    'label_length'              => '标签长度为1-100个字符',
    'value_require'             => '值不能为空',
    'value_length'              => '值长度为1-100个字符',
    'tag_type_max'              => '标签类型不能超过50个字符',

    // 配置
    'config_value_require'      => '配置值不能为空',
    'configs_require'           => '配置数据不能为空',
    'configs_array'             => '配置数据格式错误',

    // 定时任务
    'task_name_require'         => '请输入任务名称',
    'task_name_max'             => '任务名称最多100个字符',
    'command_require'           => '请输入执行命令',
    'command_max'               => '执行命令最多255个字符',
    'expression_require'        => '请输入Cron表达式',
    'expression_max'            => 'Cron表达式最多100个字符',

    // 通知
    'notification_title_require' => '请输入通知标题',
    'notification_title_max'    => '标题最多200个字符',
    'notification_content_require' => '请输入通知内容',
    'notification_type_require' => '请选择通知类型',
    'notification_type_invalid' => '通知类型无效',

    // 通用
    'name_require'              => '名称不能为空',
    'title_require'             => '标题不能为空',
    'status_require'            => '状态不能为空',
    'id_require'                => 'ID不能为空',
    'id_integer'                => 'ID必须是整数',
    'id_gt_zero'                => 'ID必须大于0',
    'page_integer'              => '页码必须是整数',
    'page_gt_zero'              => '页码必须大于0',
    'limit_integer'             => '每页数量必须是整数',
    'limit_between'             => '每页数量必须在1-100之间',
    'status_integer'            => '状态必须是整数',
    'remark_max'                => '备注最多255个字符',
];
