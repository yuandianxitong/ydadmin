<?php

declare(strict_types=1);

namespace app\adminapi\controller\system;

use app\service\system\ApiDocService;
use core\apidoc\OpenApiDocument;
use core\base\Controller;
use core\permission\PermissionSkip;
use DI\Attribute\Inject;
use support\Response;
use Webman\Http\Request;

/**
 * API 文档（spec §3/§8）。两个端点前端硬编码、不可改：
 *   GET /adminapi/system/api-doc/openapi.json?type=admin|api  裸 OpenAPI 3.0 JSON
 *   GET /adminapi/system/api-doc?type=admin|api               独立 Swagger 页（HTML）
 * 两条都标 PermissionSkip 且必须公开（config/route.php 只在 APP_DEBUG=true 时注册）：
 * 前端「Swagger UI」「下载 JSON」按钮走 window.open，浏览器导航带不了 Authorization 头。
 */
class ApiDocController extends Controller
{
    #[Inject]
    protected ApiDocService $apiDocService;

    #[PermissionSkip]
    public function openapi(Request $request): Response
    {
        // $request->get('type') 在 ?type[]=x 这种写法下会是数组：这是公开端点，不能因为一个不合法的
        // 查询参数就抛未捕获异常变成 500。归一只有 ApiDocService::normalizeType() 一处，index() 同用。
        $document = $this->apiDocService->document($this->apiDocService->normalizeType($request->get('type')));
        // 空 paths / 空 properties 编成合法 OpenAPI 对象（{} 而不是 []）——唯一实现在
        // OpenApiDocument::toJson()，这里不再自己写一遍 stdClass 特判。
        $body = OpenApiDocument::toJson($document);

        return new Response(200, ['Content-Type' => 'application/json; charset=utf-8'], $body);
    }

    #[PermissionSkip]
    public function index(Request $request): Response
    {
        // 拼进 HTML 前的值必然 ∈ {admin, api}：与 openapi() 同一个归一函数，不另写白名单。
        $type = $this->apiDocService->normalizeType($request->get('type'));

        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $this->page($type));
    }

    private function page(string $type): string
    {
        $jsonUrl = '/adminapi/system/api-doc/openapi.json?type=' . $type;

        return <<<HTML
        <!doctype html>
        <html lang="zh-CN">
        <head>
        <meta charset="utf-8">
        <title>API 文档</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui.css">
        </head>
        <body>
        <div id="swagger-ui"></div>
        <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
        <script>
        (function () {
            function readToken() {
                try {
                    var raw = window.localStorage.getItem('ydadmin_token');
                    if (!raw) { return ''; }
                    var data = JSON.parse(raw);
                    if (data.expire && data.expire < Math.round(Date.now() / 1000)) { return ''; }
                    return data.value || '';
                } catch (e) {
                    return '';
                }
            }
            window.SwaggerUIBundle({
                url: '{$jsonUrl}',
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [SwaggerUIBundle.presets.apis, SwaggerUIBundle.SwaggerUIStandalonePreset],
                layout: 'BaseLayout',
                requestInterceptor: function (req) {
                    var token = readToken();
                    if (token) { req.headers.Authorization = 'Bearer ' + token; }
                    return req;
                }
            });
        })();
        </script>
        </body>
        </html>
        HTML;
    }
}
