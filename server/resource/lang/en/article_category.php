<?php

// 由代码生成器生成（en）。business.php / validation.php 是手写模块共用文件，
// 生成模块用独立分组 article_category.*，键统一加 article_category_ 前缀，同模块以后生成别的模型不会互相覆盖。
return [
    'not_found' => 'Article category not found',
    'name_exists' => 'Category name already exists',
    'parent_invalid' => 'Parent category is invalid',
    'has_children' => 'Cannot delete a category that has child categories',
    'has_articles' => 'Cannot delete a category that has articles',
    'article_category_not_found' => 'ArticleCategory not found',
    'article_category_parent_id_require' => 'Parent Id is required',
    'article_category_parent_id_integer' => 'Parent Id must be an integer',
    'article_category_name_require' => 'Name is required',
    'article_category_name_length' => 'Name must not exceed 100 characters',
    'article_category_icon_require' => 'Icon is required',
    'article_category_icon_length' => 'Icon must not exceed 255 characters',
    'article_category_sort_require' => 'Sort is required',
    'article_category_sort_integer' => 'Sort must be an integer',
    'article_category_sort_min' => 'Sort must not be less than 0',
    'article_category_status_require' => 'Status is required',
    'article_category_status_integer' => 'Status must be an integer',
    'article_category_status_invalid' => 'Status is invalid',
    'article_category_ids_require' => 'Please select records to delete',
    'article_category_ids_integer' => 'ID must be an integer',
];
