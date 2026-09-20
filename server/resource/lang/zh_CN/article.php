<?php

// 由代码生成器生成（zh_CN）。business.php / validation.php 是手写模块共用文件，
// 生成模块用独立分组 article.*，键统一加 article_ 前缀，同模块以后生成别的模型不会互相覆盖。
return [
    'article_not_found' => '文章不存在',
    'article_category_id_require' => '栏目ID不能为空',
    'article_category_id_integer' => '栏目ID必须是整数',
    'article_title_require' => '标题不能为空',
    'article_title_length' => '标题长度不能超过200个字符',
    'article_cover_require' => '封面图不能为空',
    'article_cover_length' => '封面图长度不能超过255个字符',
    'article_summary_require' => '摘要不能为空',
    'article_summary_length' => '摘要长度不能超过500个字符',
    'article_content_require' => '内容不能为空',
    'article_tags_array' => '标签JSON数组必须是数组',
    'article_author_require' => '作者不能为空',
    'article_author_length' => '作者长度不能超过50个字符',
    'article_view_count_require' => '阅读量不能为空',
    'article_view_count_integer' => '阅读量必须是整数',
    'article_status_require' => '0草稿 1已发布不能为空',
    'article_status_integer' => '0草稿 1已发布必须是整数',
    'article_status_invalid' => '0草稿 1已发布的值无效',
    'article_publish_at_date' => '发布时间格式不正确',
    'article_ids_require' => '请选择要删除的数据',
    'article_ids_integer' => 'ID必须是整数',
];
