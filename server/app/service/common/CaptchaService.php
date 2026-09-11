<?php

declare(strict_types=1);

namespace app\service\common;

use support\Cache;

/**
 * 图形验证码：130×40 PNG，4 位（排除易混淆的 0O1lI），缓存 300 秒，校验不区分大小写，
 * 校验后立即删除（一次性）。
 *
 * 简化点（相对旧版）：旧版优先探测 TrueType 字体（项目内置字体或系统字体路径），
 * 找不到时回退内置位图字体；新仓库未随包携带 ttf 字体文件，直接使用 GD 内置位图字体
 * （imagechar），不做 TTF 探测，视觉效果略朴素但功能等价。
 */
class CaptchaService
{
    private const CACHE_PREFIX = 'captcha.';

    private const EXPIRE = 300;

    private const WIDTH = 130;

    private const HEIGHT = 40;

    private const LENGTH = 4;

    /** @return array{key: string, image: string} */
    public function generate(): array
    {
        $key = $this->generateKey();
        $code = $this->generateCode();

        Cache::set(self::CACHE_PREFIX . $key, strtolower($code), self::EXPIRE);

        return [
            'key'   => $key,
            'image' => 'data:image/png;base64,' . base64_encode($this->createImage($code)),
        ];
    }

    /** 校验后立即删除，防止重复使用。 */
    public function verify(string $key, string $code): bool
    {
        if ($key === '' || $code === '') {
            return false;
        }

        $cacheKey = self::CACHE_PREFIX . $key;
        $cached = Cache::get($cacheKey);
        if ($cached === null) {
            return false;
        }

        Cache::delete($cacheKey);

        return strtolower($code) === $cached;
    }

    private function generateKey(): string
    {
        return md5(uniqid((string) mt_rand(), true));
    }

    private function generateCode(): string
    {
        // 排除容易混淆的字符: 0O1lI
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        $code = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= $chars[mt_rand(0, strlen($chars) - 1)];
        }

        return $code;
    }

    private function createImage(string $code): string
    {
        $width = self::WIDTH;
        $height = self::HEIGHT;

        $image = imagecreatetruecolor($width, $height);
        if ($image === false) {
            // GD 不可用时返回空白（调用方仍拿到合法的空 base64 内容，不阻断登录页渲染）
            return '';
        }

        $bgColor = imagecolorallocate($image, mt_rand(230, 250), mt_rand(230, 250), mt_rand(230, 250));
        imagefill($image, 0, 0, $bgColor);

        for ($i = 0; $i < 4; $i++) {
            $lineColor = imagecolorallocate($image, mt_rand(100, 200), mt_rand(100, 200), mt_rand(100, 200));
            imageline($image, mt_rand(0, $width), mt_rand(0, $height), mt_rand(0, $width), mt_rand(0, $height), $lineColor);
        }

        for ($i = 0; $i < 50; $i++) {
            $dotColor = imagecolorallocate($image, mt_rand(100, 220), mt_rand(100, 220), mt_rand(100, 220));
            imagesetpixel($image, mt_rand(0, $width), mt_rand(0, $height), $dotColor);
        }

        $font = 5; // GD 内置最大位图字体（1-5）
        $charSpacing = (int) ($width / (strlen($code) + 1));
        for ($i = 0; $i < strlen($code); $i++) {
            $charColor = imagecolorallocate($image, mt_rand(20, 100), mt_rand(20, 100), mt_rand(20, 100));
            $x = $charSpacing * ($i + 1) - 5;
            $y = (int) ($height / 2) - 8 + mt_rand(-3, 3);
            imagechar($image, $font, $x, $y, $code[$i], $charColor);
        }

        ob_start();
        imagepng($image);
        $data = ob_get_clean();

        return $data !== false ? $data : '';
    }
}
