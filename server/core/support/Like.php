<?php

declare(strict_types=1);

namespace core\support;

/**
 * LIKE 模糊匹配的安全拼接：先转义通配符 % _ 与转义符 \（MySQL 默认转义符是反斜杠，
 * 前提是 sql_mode 不含 NO_BACKSLASH_ESCAPES），再两端加 %。
 * 用户输入的 keyword 一律经它进入 LIKE：否则 "%" 会匹配全表，"_" 会匹配任意单个字符。
 * 纯函数，无状态。
 */
final class Like
{
    public static function contains(string $keyword): string
    {
        return '%' . addcslashes($keyword, '%_\\') . '%';
    }
}
