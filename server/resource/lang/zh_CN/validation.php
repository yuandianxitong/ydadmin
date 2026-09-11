<?php

return [
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
