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
    'dept_out_of_scope'            => '不能把管理员分配到数据权限范围外的部门',
    'cannot_change_own_department' => '不能修改自己的所属部门',
];
