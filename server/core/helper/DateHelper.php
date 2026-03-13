<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace core\helper;

class DateHelper
{
    /**
     * 格式化日期
     */
    public static function format($date, string $format = 'Y-m-d H:i:s'): string
    {
        if (is_string($date)) {
            $date = strtotime($date);
        } elseif ($date instanceof \DateTime) {
            $date = $date->getTimestamp();
        }

        return date($format, $date);
    }

    /**
     * 人性化时间显示
     */
    public static function diffForHumans($date): string
    {
        if (is_string($date)) {
            $date = strtotime($date);
        } elseif ($date instanceof \DateTime) {
            $date = $date->getTimestamp();
        }

        $now = time();
        $diff = $now - $date;

        if ($diff < 60) {
            return '刚刚';
        } elseif ($diff < 3600) {
            return floor($diff / 60) . '分钟前';
        } elseif ($diff < 86400) {
            return floor($diff / 3600) . '小时前';
        } elseif ($diff < 2592000) {
            return floor($diff / 86400) . '天前';
        } elseif ($diff < 31536000) {
            return floor($diff / 2592000) . '个月前';
        } else {
            return floor($diff / 31536000) . '年前';
        }
    }

    /**
     * 获取日期范围
     */
    public static function getDateRange(string $type = 'today'): array
    {
        $today = date('Y-m-d');

        switch ($type) {
            case 'today':
                return [$today . ' 00:00:00', $today . ' 23:59:59'];

            case 'yesterday':
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                return [$yesterday . ' 00:00:00', $yesterday . ' 23:59:59'];

            case 'week':
                $start = date('Y-m-d', strtotime('monday this week'));
                $end = date('Y-m-d', strtotime('sunday this week'));
                return [$start . ' 00:00:00', $end . ' 23:59:59'];

            case 'month':
                $start = date('Y-m-01');
                $end = date('Y-m-t');
                return [$start . ' 00:00:00', $end . ' 23:59:59'];

            case 'year':
                $start = date('Y-01-01');
                $end = date('Y-12-31');
                return [$start . ' 00:00:00', $end . ' 23:59:59'];

            default:
                return [$today . ' 00:00:00', $today . ' 23:59:59'];
        }
    }

    /**
     * 验证日期格式
     */
    public static function validate(string $date, string $format = 'Y-m-d'): bool
    {
        $dateTime = \DateTime::createFromFormat($format, $date);
        return $dateTime && $dateTime->format($format) === $date;
    }

    /**
     * 计算年龄
     */
    public static function age(string $birthday): int
    {
        $birthDate = new \DateTime($birthday);
        $today = new \DateTime('today');
        return $birthDate->diff($today)->y;
    }

    /**
     * 获取星期几
     */
    public static function dayOfWeek($date): string
    {
        if (is_string($date)) {
            $date = strtotime($date);
        }

        $days = ['日', '一', '二', '三', '四', '五', '六'];
        return '星期' . $days[date('w', $date)];
    }

    /**
     * 判断是否为工作日
     */
    public static function isWeekday($date): bool
    {
        if (is_string($date)) {
            $date = strtotime($date);
        }

        $dayOfWeek = date('w', $date);
        return $dayOfWeek >= 1 && $dayOfWeek <= 5;
    }

    /**
     * 判断是否为周末
     */
    public static function isWeekend($date): bool
    {
        return !self::isWeekday($date);
    }
}
