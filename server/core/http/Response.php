<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace core\http;

use think\Response as ThinkResponse;

class Response
{
    /**
     * 成功响应
     */
    public static function success(string $message = '', $data = [], int $code = 200): ThinkResponse
    {
        return json([
            'code' => $code,
            'message' => $message ?: lang('messages.success'),
            'data' => $data,
            'timestamp' => time()
        ]);
    }

    /**
     * 错误响应
     */
    public static function error(string $message = '', int $code = 400, $data = []): ThinkResponse
    {
        return json([
            'code' => $code,
            'message' => $message ?: lang('messages.error'),
            'data' => $data,
            'timestamp' => time()
        ]);
    }

    /**
     * 分页响应
     */
    public static function paginate(array $data, string $message = ''): ThinkResponse
    {
        return json([
            'code' => 200,
            'message' => $message ?: lang('messages.get_success'),
            'data' => $data,
            'timestamp' => time()
        ]);
    }

    /**
     * 列表响应
     */
    public static function list(array $list, string $message = ''): ThinkResponse
    {
        return json([
            'code' => 200,
            'message' => $message ?: lang('messages.get_success'),
            'data' => [
                'list' => $list,
                'total' => count($list)
            ],
            'timestamp' => time()
        ]);
    }

    /**
     * 无内容响应
     */
    public static function noContent(): ThinkResponse
    {
        return response('', 204);
    }

    /**
     * 重定向响应
     */
    public static function redirect(string $url, int $code = 302): ThinkResponse
    {
        return redirect($url, $code);
    }

    /**
     * 文件下载响应
     */
    public static function download(string $file, string $name = '', bool $expire = true): ThinkResponse
    {
        return download($file, $name, $expire);
    }

    /**
     * 图片响应
     */
    public static function image(string $file): ThinkResponse
    {
        return response(file_get_contents($file), 200, [
            'Content-Type' => 'image/' . pathinfo($file, PATHINFO_EXTENSION)
        ]);
    }
}
