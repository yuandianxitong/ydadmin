<?php

declare(strict_types=1);

namespace app\service\common;

use support\Redis;

/**
 * 图形验证码：130×40 PNG，4 位（排除易混淆的 0O1lI），存 Redis `captcha.{key}` 300 秒，校验不区分大小写，
 * 校验后立即删除（一次性）。
 *
 * 随机性：key 取 random_bytes(16) 的十六进制，字符用 random_int 抽取（mt_rand 在 mt_srand 固定种子后可复现）；
 * 画面噪点与安全无关，仍用 mt_rand。
 * 原子性：直接经 support\Redis 存取明文（不走 support\Cache），校验时 GET 与 DEL 放在同一个 MULTI 里，
 * 并发提交同一个验证码只有一次能读到值。
 *
 * 简化点（相对旧版）：旧版优先探测 TrueType 字体（项目内置字体或系统字体路径），
 * 找不到时回退内置位图字体；新仓库未随包携带 ttf 字体文件，直接使用 GD 内置位图字体
 * （imagechar），不做 TTF 探测，视觉效果略朴素但功能等价。
 */
class CaptchaService
{
    private const KEY_PREFIX = 'captcha.';

    private const EXPIRE = 300;

    private const WIDTH = 130;

    private const HEIGHT = 40;

    private const LENGTH = 4;

    /** @return array{key: string, image: string} */
    public function generate(): array
    {
        $key = bin2hex(random_bytes(16));
        $code = $this->generateCode();

        Redis::setEx(self::KEY_PREFIX . $key, self::EXPIRE, strtolower($code));

        return [
            'key'   => $key,
            'image' => 'data:image/png;base64,' . base64_encode($this->createImage($code)),
        ];
    }

    /** 校验后立即删除，防止重复使用；GET 与 DEL 在同一个 MULTI 里执行，并发校验只有一次拿得到值。 */
    public function verify(string $key, string $code): bool
    {
        if ($key === '' || $code === '') {
            return false;
        }

        $redisKey = self::KEY_PREFIX . $key;
        $result = Redis::multi()->get($redisKey)->del($redisKey)->exec();
        $cached = is_array($result) ? ($result[0] ?? false) : false;
        if (!is_string($cached) || $cached === '') {
            return false;
        }

        return hash_equals($cached, strtolower($code));
    }

    private function generateCode(): string
    {
        // 排除容易混淆的字符: 0O1lI
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        $code = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
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
