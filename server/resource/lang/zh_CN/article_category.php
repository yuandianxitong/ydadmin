<?php

// 由代码生成器生成（zh_CN）。business.php / validation.php 是手写模块共用文件，
// 生成模块用独立分组 article_category.*，键统一加 article_category_ 前缀，同模块以后生成别的模型不会互相覆盖。
return [
    'not_found' => '文章栏目不存在',
    'name_exists' => '栏目名称已存在',
    'parent_invalid' => '上级栏目无效',
    'has_children' => '该栏目下存在子栏目，无法删除',
    'has_articles' => '该栏目下存在文章，无法删除',
    'article_category_not_found' => '文章栏目不存在',
    'article_category_parent_id_require' => '父栏目ID不能为空',
    'article_category_parent_id_integer' => '父栏目ID必须是整数',
    'article_category_name_require' => '栏目名称不能为空',
    'article_category_name_length' => '栏目名称长度不能超过100个字符',
    'article_category_icon_require' => '栏目图标不能为空',
    'article_category_icon_length' => '栏目图标长度不能超过255个字符',
    'article_category_sort_require' => '排序不能为空',
    'article_category_sort_integer' => '排序必须是整数',
    'article_category_sort_min' => '排序不能小于0',
    'article_category_status_require' => '状态:1启用 0禁用不能为空',
    'article_category_status_integer' => '状态:1启用 0禁用必须是整数',
    'article_category_status_invalid' => '状态:1启用 0禁用的值无效',
    'article_category_ids_require' => '请选择要删除的数据',
    'article_category_ids_integer' => 'ID必须是整数',
];
