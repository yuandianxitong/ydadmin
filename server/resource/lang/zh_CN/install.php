<?php

return [
    'already_installed'   => '系统已安装，不能重复执行安装。',
    'not_installed'       => '系统尚未安装',
    'database_not_empty'  => '目标数据库已有数据表，拒绝安装以免覆盖现有数据。',
    'sql_failed'          => '执行安装 SQL 失败，数据库可能处于半成品状态，请人工丢弃该库后重试。',
    'baseline_required'   => '当前库没有升级记录，请使用 --baseline 指定已有版本后再升级。',
    'invalid_update_dir'  => '升级目录名称或路径不合法，已拒绝加载。',
    'restart_hint'        => '安装完成。请执行 php start.php restart 使新配置生效。',
    'env_php'             => 'PHP 版本必须不低于 8.4。',
    'env_extension'       => '必须安装 :name 扩展。',
    'env_writable'        => ':path 必须可写。',
];
