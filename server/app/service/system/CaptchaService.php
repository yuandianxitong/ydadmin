<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\service\system;

use think\facade\Cache;

class CaptchaService
{
    /** 验证码缓存前缀 */
    protected string $cachePrefix = 'captcha:';

    /** 验证码过期时间（秒） */
    protected int $expire = 300;

    /** 图片宽度 */
    protected int $width = 130;

    /** 图片高度 */
    protected int $height = 40;

    /** 验证码长度 */
    protected int $length = 4;

    /**
     * 生成验证码
     * @return array{key: string, image: string}
     */
    public function generate(): array
    {
        $key = $this->generateKey();
        $code = $this->generateCode();

        // 缓存验证码（存小写，方便不区分大小写验证）
        Cache::set($this->cachePrefix . $key, strtolower($code), $this->expire);

        // 生成图片
        $image = $this->createImage($code);

        return [
            'key' => $key,
            'image' => 'data:image/png;base64,' . base64_encode($image),
        ];
    }

    /**
     * 校验验证码
     */
    public function verify(string $key, string $code): bool
    {
        if (empty($key) || empty($code)) {
            return false;
        }

        $cacheKey = $this->cachePrefix . $key;
        $cachedCode = Cache::get($cacheKey);

        if ($cachedCode === null) {
            return false;
        }

        // 验证后立即删除，防止重复使用
        Cache::delete($cacheKey);

        return strtolower($code) === $cachedCode;
    }

    /**
     * 生成唯一 key
     */
    protected function generateKey(): string
    {
        return md5(uniqid((string)mt_rand(), true));
    }

    /**
     * 生成随机验证码字符
     */
    protected function generateCode(): string
    {
        // 排除容易混淆的字符: 0O1lI
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        $code = '';
        for ($i = 0; $i < $this->length; $i++) {
            $code .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return $code;
    }

    /**
     * 使用 GD 库生成验证码图片
     */
    protected function createImage(string $code): string
    {
        $width = $this->width;
        $height = $this->height;

        $image = imagecreatetruecolor($width, $height);
        if ($image === false) {
            // GD 不可用时返回空白 PNG
            return '';
        }

        // 背景色 - 浅色随机
        $bgColor = imagecolorallocate($image, mt_rand(230, 250), mt_rand(230, 250), mt_rand(230, 250));
        imagefill($image, 0, 0, $bgColor);

        // 绘制干扰线
        for ($i = 0; $i < 4; $i++) {
            $lineColor = imagecolorallocate($image, mt_rand(100, 200), mt_rand(100, 200), mt_rand(100, 200));
            imageline(
                $image,
                mt_rand(0, $width),
                mt_rand(0, $height),
                mt_rand(0, $width),
                mt_rand(0, $height),
                $lineColor
            );
        }

        // 绘制干扰点
        for ($i = 0; $i < 50; $i++) {
            $dotColor = imagecolorallocate($image, mt_rand(100, 220), mt_rand(100, 220), mt_rand(100, 220));
            imagesetpixel($image, mt_rand(0, $width), mt_rand(0, $height), $dotColor);
        }

        // 绘制验证码字符
        $fontSize = 22;
        $charSpacing = (int)($width / (strlen($code) + 1));

        for ($i = 0; $i < strlen($code); $i++) {
            $charColor = imagecolorallocate($image, mt_rand(20, 100), mt_rand(20, 100), mt_rand(20, 100));
            $angle = mt_rand(-15, 15);
            $x = $charSpacing * ($i + 1) - (int)($fontSize / 2);
            $y = (int)($height / 2) + (int)($fontSize / 2) + mt_rand(-3, 3);

            // 优先使用 TrueType 字体
            $font = $this->getFont();
            if ($font !== '') {
                imagettftext($image, (float)$fontSize, $angle, $x, $y, $charColor, $font, $code[$i]);
            } else {
                // 回退：用内置字体放大绘制
                imagechar($image, 5, $x, (int)($height / 2) - 8 + mt_rand(-3, 3), $code[$i], $charColor);
            }
        }

        // 输出为 PNG
        ob_start();
        imagepng($image);
        $data = ob_get_clean();
        imagedestroy($image);

        return $data ?: '';
    }

    /**
     * 获取可用的 TrueType 字体路径
     */
    protected function getFont(): string
    {
        // 常见系统字体路径
        $candidates = [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
            '/usr/share/fonts/truetype/freefont/FreeSansBold.ttf',
            '/System/Library/Fonts/Helvetica.ttc',        // macOS
            '/System/Library/Fonts/SFNSMono.ttf',         // macOS
            'C:/Windows/Fonts/arial.ttf',                  // Windows
        ];

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return '';
    }
}
