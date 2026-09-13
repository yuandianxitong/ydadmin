<?php

// 业务规则错误（BusinessException：HTTP 200 + code 400）
return [
    // 菜单
    'menu_name_exists'          => '菜单名称已存在',
    'route_path_exists'         => '路由路径已存在',
    'parent_menu_not_found'     => '父级菜单不存在',
    'button_no_children'        => '按钮类型菜单不能有子菜单',
    'menu_not_found'            => '菜单不存在',
    'parent_not_self'           => '不能设置自己为父级菜单',
    'parent_not_child'          => '不能设置子菜单为父级菜单',
    'menu_has_children'         => '该菜单下还有子菜单，不能删除',
    'menu_used_by_role'         => '该菜单已被角色使用，不能删除',
    'sort_field_missing'        => '排序项缺少必要字段',
    'sort_field_type_error'     => '排序字段类型不正确',
    'sort_parent_mismatch'      => '存在不属于同一父级的菜单，禁止排序',
    'sort_data_required'        => '排序数据不能为空',
    'please_select_menu'        => '请选择要删除的菜单',

    // 角色
    'role_code_exists'          => '角色标识已存在',
    'role_not_found'            => '角色不存在',
    'system_role_no_modify'     => '系统角色不能修改标识',
    'system_role_no_delete'     => '系统角色不能删除',
    'role_has_admins'           => '该角色下还有管理员，不能删除',
    'system_role_no_status'     => '系统角色不允许修改状态',
    'system_role_no_permission' => '系统角色权限不可修改',
    'system_role_no_assign'     => '只有超级管理员可以分配系统角色',
    'please_select_role'        => '请选择要删除的角色',

    // 部门
    'dept_code_exists'          => '部门编码已存在',
    'parent_dept_not_found'     => '上级部门不存在',
    'dept_not_found'            => '部门不存在',
    'dept_parent_not_self'      => '上级部门不能是自己',
    'dept_parent_not_child'     => '上级部门不能是当前部门的子部门',
    'dept_has_children'         => '该部门下存在子部门，无法删除',
    'dept_has_admins'           => '该部门下存在管理员，无法删除',

    // 管理员
    'please_select_admin'          => '请选择要删除的管理员',
    'dept_out_of_scope'            => '部门不在你的数据权限范围内',
    'cannot_change_own_department' => '不能修改自己的所属部门',
    'cannot_change_own_roles'      => '不能修改自己的角色',
    'role_exceeds_own_permissions' => '不能授予超出自己权限的角色',
    'role_scope_exceeds_own'       => '不能授予超出自己数据范围的角色',

    // 数据字典
    'dict_not_found'         => '字典不存在',
    'dict_code_exists'       => '字典编码已存在',
    'dict_item_not_found'    => '字典项不存在',
    'dict_item_value_exists' => '字典项值已存在',

    // 站内通知
    'notification_not_found' => '通知不存在',

    // 系统配置
    'config_not_found'          => '配置不存在',
    'config_key_not_found'      => '配置项不存在：:key',
    'config_value_invalid'      => '配置项 :key 的值格式不正确',

    // 素材与上传
    'please_select_upload'      => '请选择要上传的文件',
    'upload_image_only'         => '只能上传图片文件',
    'file_type_not_allowed'     => '不允许上传该类型的文件',
    'image_size_exceeded'       => '图片大小不能超过 :size MB',
    'file_size_exceeded'        => '文件大小不能超过 :size MB',
    'file_not_found'            => '文件不存在',

    // 存储
    'storage_driver_unsupported' => '不支持的存储驱动：:driver',
    'storage_config_incomplete'  => '云存储配置不完整，请在「系统配置 - 存储配置」里填写完整后再上传',
    'storage_oss_region_required' => '请先配置 OSS Region（或使用标准 Endpoint 以便自动推导）',
    'storage_upload_failed'      => '文件上传到 :driver 失败：:error',
    'storage_delete_failed'      => ':driver 上的文件删除失败：:error',
];
