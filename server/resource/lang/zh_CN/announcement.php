<?php

// 由代码生成器生成（zh_CN）。business.php / validation.php 是手写模块共用文件，
// 生成模块用独立分组 announcement.*，键统一加 announcement_ 前缀，同模块以后生成别的模型不会互相覆盖。
return [
    'announcement_not_found' => '公告不存在',
    'announcement_title_require' => '标题不能为空',
    'announcement_title_length' => '标题长度不能超过200个字符',
    'announcement_type_require' => '1通知 2更新 3活动不能为空',
    'announcement_type_integer' => '1通知 2更新 3活动必须是整数',
    'announcement_status_require' => '0草稿 1已发布不能为空',
    'announcement_status_integer' => '0草稿 1已发布必须是整数',
    'announcement_status_invalid' => '0草稿 1已发布的值无效',
    'announcement_sort_require' => '排序不能为空',
    'announcement_sort_integer' => '排序必须是整数',
    'announcement_sort_min' => '排序不能小于0',
    'announcement_publish_at_date' => '发布时间格式不正确',
    'announcement_ids_require' => '请选择要删除的数据',
    'announcement_ids_integer' => 'ID必须是整数',
];
