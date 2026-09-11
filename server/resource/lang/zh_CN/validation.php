<?php

return [
    // 应用级校验消息：控制器 messages() 里以 'validation.xxx' 引用
    'username_require'     => '用户名不能为空',
    'username_length_3_50' => '用户名长度为3-50个字符',
    'password_require'     => '密码不能为空',
    'password_length'      => '密码长度为6-20个字符',
    'captcha_require'      => '请输入验证码',
    'captcha_length'       => '验证码长度不正确',

    'required'   => ':attribute 不能为空',
    'string'     => ':attribute 必须是字符串',
    'integer'    => ':attribute 必须是整数',
    'numeric'    => ':attribute 必须是数字',
    'boolean'    => ':attribute 必须是布尔值',
    'array'      => ':attribute 必须是数组',
    'email'      => ':attribute 格式不正确',
    'url'        => ':attribute 必须是有效的 URL',
    'date'       => ':attribute 不是有效的日期',
    'in'         => ':attribute 的值无效',
    'regex'      => ':attribute 格式不正确',
    'alpha_dash' => ':attribute 只能包含字母、数字、短横线和下划线',
    'confirmed'  => ':attribute 两次输入不一致',
    'unique'     => ':attribute 已存在',
    'exists'     => ':attribute 不存在',
    'different'  => ':attribute 必须与 :other 不同',
    'required_if' => ':attribute 不能为空',
    'max' => [
        'numeric' => ':attribute 不能大于 :max',
        'string'  => ':attribute 不能超过 :max 个字符',
        'array'   => ':attribute 最多 :max 项',
        'file'    => ':attribute 不能超过 :max KB',
    ],
    'min' => [
        'numeric' => ':attribute 不能小于 :min',
        'string'  => ':attribute 至少 :min 个字符',
        'array'   => ':attribute 至少 :min 项',
        'file'    => ':attribute 不能小于 :min KB',
    ],
    'between' => [
        'numeric' => ':attribute 必须在 :min 到 :max 之间',
        'string'  => ':attribute 长度必须在 :min 到 :max 个字符之间',
        'array'   => ':attribute 必须有 :min 到 :max 项',
        'file'    => ':attribute 必须在 :min 到 :max KB 之间',
    ],
];
