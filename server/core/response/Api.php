<?php

declare(strict_types=1);

namespace core\response;

use support\Response;

/**
 * 统一响应构造。业务错误 HTTP 恒为 200，业务码走 body.code；
 * 只有 errorWithStatus() 让 HTTP 状态跟随业务码（5xx、未安装 503 等）。
 * 默认文案按当前请求的 locale 经 lang() 解析。
 */
final class Api
{
    public static function success(mixed $data = [], ?string $message = null): Response
    {
        return self::json(200, $message ?? lang('messages.success'), $data);
    }

    public static function error(string $message, int $code = 400, mixed $data = []): Response
    {
        return self::json($code, $message, $data);
    }

    public static function errorWithStatus(string $message, int $code, mixed $data = []): Response
    {
        return self::json($code, $message, $data, $code);
    }

    /**
     * @param array{list: array<int, mixed>, pagination: array<string, int>} $result Repository::getList() 的返回结构
     */
    public static function paginate(array $result, ?string $message = null): Response
    {
        return self::json(200, $message ?? lang('messages.success'), $result);
    }

    private static function json(int $code, string $message, mixed $data, int $httpStatus = 200): Response
    {
        $body = json_encode([
            'code'      => $code,
            'message'   => $message,
            'data'      => $data,
            'timestamp' => time(),
        ], JSON_UNESCAPED_UNICODE);

        if ($body === false) {
            // 非法 UTF-8 等导致序列化失败：返回固定安全文案，不回显原始字节
            $httpStatus = 500;
            $fallback = json_encode(lang('messages.response_encode_failed'), JSON_UNESCAPED_UNICODE) ?: '""';
            $body = sprintf('{"code":500,"message":%s,"data":[],"timestamp":%d}', $fallback, time());
        }

        return new Response($httpStatus, ['Content-Type' => 'application/json'], $body);
    }
}
