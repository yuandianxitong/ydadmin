<?php
declare(strict_types=1);

namespace app\adminapi\controller\v1\system;

use core\base\Controller;
use think\Response;
use OpenApi\Attributes as OA;

class ApiDocController extends Controller
{
    /**
     * Swagger UI 页面
     */
    public function index(): Response
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>元点Admin API 文档</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui.css">
    <style>
        body { margin: 0; background: #fafafa; }
        .swagger-ui .topbar { display: none; }
        .swagger-ui .info { margin: 20px 0; }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script>
        SwaggerUIBundle({
            url: window.location.pathname.replace('/api-doc', '/api-doc/openapi.json'),
            dom_id: '#swagger-ui',
            deepLinking: true,
            presets: [SwaggerUIBundle.presets.apis, SwaggerUIBundle.SwaggerUIStandalonePreset],
            layout: "BaseLayout",
            defaultModelsExpandDepth: -1,
            docExpansion: "list",
            filter: true,
            persistAuthorization: true,
        });
    </script>
</body>
</html>
HTML;

        return response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * 生成 OpenAPI JSON
     */
    #[OA\Get(
        path: '/system/api-doc/openapi.json',
        summary: '获取OpenAPI文档JSON',
        security: [['bearerAuth' => []]],
        tags: ['系统工具'],
        responses: [
            new OA\Response(response: 200, description: 'OpenAPI JSON文档')
        ]
    )]
    public function openapi(): Response
    {
        $scanPaths = [
            app_path() . 'controller/',
            root_path() . 'core/base/Controller.php',
        ];

        // 抑制第三方包的 deprecation warning 污染 JSON 输出
        $previousLevel = error_reporting(E_ALL & ~E_DEPRECATED);
        ob_start();

        $openapi = \OpenApi\Generator::scan($scanPaths);
        $json = $openapi->toJson();

        ob_end_clean();
        error_reporting($previousLevel);

        return response($json, 200, [
            'Content-Type' => 'application/json; charset=utf-8',
        ]);
    }
}
