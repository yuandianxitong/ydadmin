<?php

declare(strict_types=1);

namespace app\middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 静态文件中间件。禁止访问点开头的隐藏文件；加 X-Content-Type-Options: nosniff，
 * 防止浏览器把 /storage 下的上传文件嗅探成 HTML/脚本执行（上传扩展名白名单之外的第二道防线）。
 *
 * 判断前必须先 rawurldecode：webman 的 App::findFile 在 is_file 前会对路径做 urldecode，
 * 只检查原始 $request->path() 会被 /admin/%2Eenv 这类百分号编码绕过。
 */
class StaticFile implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        if (str_contains(rawurldecode($request->path()), '/.')) {
            return response('<h1>403 forbidden</h1>', 403);
        }

        /** @var Response $response */
        $response = $handler($request);
        $response->withHeaders(['X-Content-Type-Options' => 'nosniff']);

        return $response;
    }
}
