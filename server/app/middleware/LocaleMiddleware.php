<?php

declare(strict_types=1);

namespace app\middleware;

use support\Context;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 把当前请求的 locale 写入 support\Context（只写 Context，不改共享的 Translator 单例）。
 * 优先读 admin 前端发送的 think-lang（zh-cn|en），其次按 q 值解析 Accept-Language，默认 zh_CN。
 */
class LocaleMiddleware implements MiddlewareInterface
{
    public const DEFAULT_LOCALE = 'zh_CN';

    public function process(Request $request, callable $handler): Response
    {
        Context::set('locale', self::resolve(
            (string) $request->header('think-lang', ''),
            (string) $request->header('accept-language', '')
        ));

        return $handler($request);
    }

    public static function resolve(string $thinkLang, string $acceptLanguage): string
    {
        $explicit = self::mapTag(strtolower(trim($thinkLang)));
        if ($explicit !== null) {
            return $explicit;
        }

        $candidates = [];
        foreach (explode(',', $acceptLanguage) as $part) {
            $segments = explode(';', trim($part));
            $tag = strtolower(trim($segments[0]));
            if ($tag === '') {
                continue;
            }
            $q = 1.0;
            foreach (array_slice($segments, 1) as $param) {
                $param = trim($param);
                if (str_starts_with($param, 'q=')) {
                    $q = (float) substr($param, 2);
                }
            }
            $candidates[] = ['tag' => $tag, 'q' => $q];
        }
        usort($candidates, static fn (array $a, array $b): int => $b['q'] <=> $a['q']);

        foreach ($candidates as $candidate) {
            $locale = self::mapTag($candidate['tag']);
            if ($locale !== null) {
                return $locale;
            }
        }

        return self::DEFAULT_LOCALE;
    }

    private static function mapTag(string $tag): ?string
    {
        $tag = str_replace('_', '-', $tag);

        return match (true) {
            $tag === 'zh' || str_starts_with($tag, 'zh-') => 'zh_CN',
            $tag === 'en' || str_starts_with($tag, 'en-') => 'en',
            default => null,
        };
    }
}
