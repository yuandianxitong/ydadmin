<?php

declare(strict_types=1);

namespace core\generator;

/** 检测确定的编码损坏和可逆的中文乱码，不猜测或自动改写业务注释。 */
final class CommentEncoding
{
    public static function isSuspicious(string $text): bool
    {
        if (!mb_check_encoding($text, 'UTF-8') || str_contains($text, "\u{FFFD}") || preg_match('/\?{2,}/', $text) === 1) {
            return true;
        }
        foreach (['Windows-1252', 'GB18030'] as $encoding) {
            $bytes = mb_convert_encoding($text, $encoding, 'UTF-8');
            if (mb_convert_encoding($bytes, 'UTF-8', $encoding) !== $text || !mb_check_encoding($bytes, 'UTF-8')) {
                continue;
            }
            if ($bytes !== $text && mb_strlen($bytes, 'UTF-8') < mb_strlen($text, 'UTF-8')
                && preg_match('/\p{Han}/u', $bytes) === 1) {
                return true;
            }
        }
        return false;
    }
}
