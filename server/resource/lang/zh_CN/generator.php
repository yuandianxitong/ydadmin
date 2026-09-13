<?php

// 代码生成器（spec §10）。本任务只用到 table_require / table_not_found；
// 其余键先在此登记，供 preview()/generate() 的后续任务直接引用，避免语言包文件被多个任务并发新建。
return [
    'table_require'           => '请选择数据表',
    'table_not_found'         => '数据表不存在',
    'disabled_in_production'  => '生产环境已禁用代码生成器',
    'file_exists'             => '目标文件已存在',
    'write_failed'            => '文件写入失败',
    'render_failed'           => '模板渲染失败',
    'invalid_module_name'     => '模块名只能以小写字母开头，由小写字母、数字、下划线组成，最长 31 个字符',
    'invalid_model_name'      => '模型名只能以大写字母开头，由字母和数字组成，最长 41 个字符',
    'module_name_reserved'    => '模块名与既有语言分组冲突（admin_log/auth/business/messages/validation/generator），请换一个模块名',
    'invalid_table_comment'   => '模块中文说明不能包含换行、回车或尖括号，最长 100 个字符',
    'unknown_error'           => '未知错误',
    'reload_hint'             => '由代码生成器生成。执行 php start.php reload 后生效。',
];
