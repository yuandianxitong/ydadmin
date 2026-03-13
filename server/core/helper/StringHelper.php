<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace core\helper;

class StringHelper
{
    /**
     * 生成随机字符串
     */
    public static function random(int $length = 10, string $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'): string
    {
        $str = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $str .= $chars[mt_rand(0, $max)];
        }
        return $str;
    }

    /**
     * 生成UUID
     */
    public static function uuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    /**
     * 驼峰转下划线
     */
    public static function snake(string $str): string
    {
        return strtolower(preg_replace('/([A-Z])/', '_$1', lcfirst($str)));
    }

    /**
     * 下划线转驼峰
     */
    public static function camel(string $str): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $str))));
    }

    /**
     * 下划线转帕斯卡
     */
    public static function pascal(string $str): string
    {
        return ucfirst(self::camel($str));
    }

    /**
     * 字符串截取
     */
    public static function substr(string $str, int $length, string $suffix = '...'): string
    {
        if (mb_strlen($str, 'utf-8') <= $length) {
            return $str;
        }

        return mb_substr($str, 0, $length, 'utf-8') . $suffix;
    }

    /**
     * 隐藏字符串
     */
    public static function mask(string $str, int $start = 3, int $length = 4, string $mask = '*'): string
    {
        $strLength = mb_strlen($str, 'utf-8');
        if ($strLength <= $start) {
            return $str;
        }

        $maskLength = min($length, $strLength - $start);
        $prefix = mb_substr($str, 0, $start, 'utf-8');
        $suffix = mb_substr($str, $start + $maskLength, null, 'utf-8');

        return $prefix . str_repeat($mask, $maskLength) . $suffix;
    }

    /**
     * 生成Slug
     */
    public static function slug(string $str, string $separator = '-'): string
    {
        $str = preg_replace('/[^\w\s\-_]/', '', $str);
        $str = preg_replace('/[\s\-_]+/', $separator, $str);
        return strtolower(trim($str, $separator));
    }

    /**
     * 检查字符串是否以指定内容开头
     */
    public static function startsWith(string $haystack, string $needle): bool
    {
        return strpos($haystack, $needle) === 0;
    }

    /**
     * 检查字符串是否以指定内容结尾
     */
    public static function endsWith(string $haystack, string $needle): bool
    {
        return substr($haystack, -strlen($needle)) === $needle;
    }

    /**
     * 检查字符串是否包含指定内容
     */
    public static function contains(string $haystack, string $needle): bool
    {
        return strpos($haystack, $needle) !== false;
    }
}
