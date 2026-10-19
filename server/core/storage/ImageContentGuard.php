<?php

declare(strict_types=1);

namespace core\storage;

/** 从临时文件检测图片类型，不信任 multipart 中客户端声明的 Content-Type。 */
final class ImageContentGuard
{
    /** @var array<string, string> */
    private const MIME_BY_EXTENSION = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp',
    ];

    public static function detect(string $path, string $extension): ?string
    {
        $expected = self::MIME_BY_EXTENSION[strtolower($extension)] ?? null;
        if ($expected === null || !is_file($path)) {
            return null;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $image = @getimagesize($path);

        return $mime === $expected && is_array($image) && $image['mime'] === $expected
            ? $mime
            : null;
    }
}
