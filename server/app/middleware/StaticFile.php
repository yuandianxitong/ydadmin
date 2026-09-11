<?php

declare(strict_types=1);

namespace app\middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 静态文件中间件。禁止访问点开头的隐藏文件；加 X-Content-Type-Options: nosniff，
 * 防止浏览器把 /storage 下的上传文件嗅探成 HTML/脚本执行（上传扩展名白名单之外的第二道防线）。
 */
class StaticFile implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        if (str_contains($request->path(), '/.')) {
            return response('<h1>403 forbidden</h1>', 403);
        }

        /** @var Response $response */
        $response = $handler($request);
        $response->withHeaders(['X-Content-Type-Options' => 'nosniff']);

        return $response;
    }
}
