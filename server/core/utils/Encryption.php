<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace core\utils;

use think\facade\Config;

class Encryption
{
    protected static string $key;
    protected static string $cipher = 'AES-256-CBC';

    /**
     * 初始化密钥
     */
    protected static function initKey(): void
    {
        if (!isset(self::$key)) {
            self::$key = Config::get('app.app_key', 'your-secret-key');
        }
    }

    /**
     * 加密数据
     */
    public static function encrypt(string $data): string
    {
        self::initKey();

        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::$cipher));
        $encrypted = openssl_encrypt($data, self::$cipher, self::$key, 0, $iv);

        return base64_encode($iv . $encrypted);
    }

    /**
     * 解密数据
     */
    public static function decrypt(string $data): string
    {
        self::initKey();

        $data = base64_decode($data);
        $ivLength = openssl_cipher_iv_length(self::$cipher);
        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);

        return openssl_decrypt($encrypted, self::$cipher, self::$key, 0, $iv);
    }

    /**
     * 生成哈希
     */
    public static function hash(string $data, string $algorithm = 'sha256'): string
    {
        return hash($algorithm, $data);
    }

    /**
     * 生成HMAC
     */
    public static function hmac(string $data, string $algorithm = 'sha256'): string
    {
        self::initKey();
        return hash_hmac($algorithm, $data, self::$key);
    }

    /**
     * 密码哈希
     */
    public static function passwordHash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * 验证密码
     */
    public static function passwordVerify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * 生成随机字符串
     */
    public static function randomString(int $length = 32): string
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * 生成签名
     */
    public static function sign(array $data, string $algorithm = 'sha256'): string
    {
        ksort($data);
        $string = http_build_query($data);
        return self::hmac($string, $algorithm);
    }

    /**
     * 验证签名
     */
    public static function verifySign(array $data, string $signature, string $algorithm = 'sha256'): bool
    {
        $expected = self::sign($data, $algorithm);
        return hash_equals($expected, $signature);
    }
}
