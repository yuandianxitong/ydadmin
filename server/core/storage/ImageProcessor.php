<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace core\storage;

use think\Image;
use core\exception\BusinessException;

class ImageProcessor
{
    protected $image;

    /**
     * 打开图片
     */
    public function open(string $path): self
    {
        try {
            $this->image = Image::open($path);
            return $this;
        } catch (\Exception $e) {
            throw new BusinessException(lang('business.image_open_failed') . ': ' . $e->getMessage());
        }
    }

    /**
     * 调整图片大小
     */
    public function resize(int $width, int $height, int $type = Image::THUMB_SCALING): self
    {
        $this->image->thumb($width, $height, $type);
        return $this;
    }

    /**
     * 裁剪图片
     */
    public function crop(int $width, int $height, int $x = 0, int $y = 0): self
    {
        $this->image->crop($width, $height, $x, $y);
        return $this;
    }

    /**
     * 旋转图片
     */
    public function rotate(int $degrees): self
    {
        $this->image->rotate($degrees);
        return $this;
    }

    /**
     * 翻转图片
     */
    public function flip(string $direction = 'x'): self
    {
        $this->image->flip($direction);
        return $this;
    }

    /**
     * 添加水印
     */
    public function watermark(string $source, int $locate = Image::WATER_SOUTHEAST, int $alpha = 80): self
    {
        $this->image->water($source, $locate, $alpha);
        return $this;
    }

    /**
     * 添加文字水印
     */
    public function textWatermark(string $text, string $font, int $size = 20, string $color = '#000000'): self
    {
        $this->image->text($text, $font, $size, $color);
        return $this;
    }

    /**
     * 保存图片
     */
    public function save(string $path, string $type = null, int $quality = 80): bool
    {
        try {
            $this->image->save($path, $type, $quality);
            return true;
        } catch (\Exception $e) {
            throw new BusinessException(lang('business.image_save_failed') . ': ' . $e->getMessage());
        }
    }

    /**
     * 获取图片信息
     */
    public function getInfo(): array
    {
        return [
            'width' => $this->image->width(),
            'height' => $this->image->height(),
            'type' => $this->image->type(),
            'mime' => $this->image->mime(),
            'size' => $this->image->size()
        ];
    }

    /**
     * 生成缩略图
     */
    public static function thumbnail(string $source, string $dest, int $width, int $height): bool
    {
        try {
            $processor = new self();
            return $processor->open($source)
                ->resize($width, $height)
                ->save($dest);
        } catch (\Exception $e) {
            throw new BusinessException(lang('business.thumbnail_failed') . ': ' . $e->getMessage());
        }
    }

    /**
     * 压缩图片
     */
    public static function compress(string $source, string $dest, int $quality = 80): bool
    {
        try {
            $processor = new self();
            return $processor->open($source)->save($dest, null, $quality);
        } catch (\Exception $e) {
            throw new BusinessException(lang('business.image_compress_failed') . ': ' . $e->getMessage());
        }
    }
}
