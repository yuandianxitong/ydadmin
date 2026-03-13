<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace core\utils;

class Validator
{
    /**
     * 验证手机号
     */
    public static function mobile(string $mobile): bool
    {
        return preg_match('/^1[3-9]\d{9}$/', $mobile) === 1;
    }

    /**
     * 验证身份证号
     */
    public static function idCard(string $idCard): bool
    {
        if (strlen($idCard) !== 18) {
            return false;
        }

        // 验证前17位是否为数字
        if (!ctype_digit(substr($idCard, 0, 17))) {
            return false;
        }

        // 验证最后一位校验码
        $factors = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];
        $checkCodes = ['1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2'];

        $sum = 0;
        for ($i = 0; $i < 17; $i++) {
            $sum += intval($idCard[$i]) * $factors[$i];
        }

        $checkCode = $checkCodes[$sum % 11];
        return strtoupper($idCard[17]) === $checkCode;
    }

    /**
     * 验证银行卡号
     */
    public static function bankCard(string $cardNumber): bool
    {
        if (!ctype_digit($cardNumber) || strlen($cardNumber) < 16 || strlen($cardNumber) > 19) {
            return false;
        }

        // Luhn算法验证
        $sum = 0;
        $alternate = false;

        for ($i = strlen($cardNumber) - 1; $i >= 0; $i--) {
            $digit = intval($cardNumber[$i]);

            if ($alternate) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit = ($digit % 10) + 1;
                }
            }

            $sum += $digit;
            $alternate = !$alternate;
        }

        return $sum % 10 === 0;
    }

    /**
     * 验证IP地址
     */
    public static function ip(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * 验证URL
     */
    public static function url(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * 验证邮箱
     */
    public static function email(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * 验证中文
     */
    public static function chinese(string $str): bool
    {
        return preg_match('/^[\x{4e00}-\x{9fa5}]+$/u', $str) === 1;
    }

    /**
     * 验证密码强度
     */
    public static function passwordStrength(string $password, int $level = 2): bool
    {
        $rules = [
            1 => '/^(?=.*[a-z]).{6,}$/',  // 至少6位，包含小写字母
            2 => '/^(?=.*[a-z])(?=.*[A-Z]).{8,}$/',  // 至少8位，包含大小写字母
            3 => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/',  // 至少8位，包含大小写字母和数字
            4 => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&]).{8,}$/'  // 至少8位，包含大小写字母、数字和特殊字符
        ];

        return isset($rules[$level]) && preg_match($rules[$level], $password) === 1;
    }

    /**
     * 验证JSON格式
     */
    public static function json(string $json): bool
    {
        json_decode($json);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * 验证日期格式
     */
    public static function date(string $date, string $format = 'Y-m-d'): bool
    {
        $dateTime = \DateTime::createFromFormat($format, $date);
        return $dateTime && $dateTime->format($format) === $date;
    }
}
