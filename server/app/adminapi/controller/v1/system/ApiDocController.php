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
        $type = (string) $this->request->param('type', 'admin');
        $openapiUrl = '/adminapi/system/api-doc/openapi.json?type=' . $type;

        $html = <<<HTML
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
            url: '{$openapiUrl}',
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
        parameters: [
            new OA\Parameter(name: 'type', in: 'query', description: '文档类型：admin=后台管理API，api=前端应用API', schema: new OA\Schema(type: 'string', enum: ['admin', 'api'], default: 'admin')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OpenAPI JSON文档')
        ]
    )]
    public function openapi(): Response
    {
        $type = (string) $this->request->param('type', 'admin');

        if ($type === 'api') {
            $scanPaths = [
                root_path() . 'app/api/controller/',
                root_path() . 'core/base/Controller.php',
            ];
            $title       = '元点Admin 前端应用 API';
            $description = '元点Admin 前端应用 RESTful API 文档';
            $servers     = [['url' => '/api', 'description' => '前端应用 API']];
        } else {
            $scanPaths = [
                app_path() . 'controller/',
                root_path() . 'core/base/Controller.php',
            ];
            $title       = '元点Admin 后台管理 API';
            $description = '元点Admin 后台管理 RESTful API 文档';
            $servers     = [['url' => '/adminapi', 'description' => '后台管理 API']];
        }

        // 抑制第三方包的 deprecation warning 污染 JSON 输出
        $previousLevel = error_reporting(E_ALL & ~E_DEPRECATED);
        ob_start();

        $openapi = \OpenApi\Generator::scan($scanPaths);

        ob_end_clean();
        error_reporting($previousLevel);

        // 覆盖 info 和 servers
        $data = json_decode($openapi->toJson(), true);
        $data['info']['title']       = $title;
        $data['info']['description'] = $description;
        $data['servers'] = array_map(fn($s) => ['url' => $s['url'], 'description' => $s['description']], $servers);

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return response($json, 200, [
            'Content-Type' => 'application/json; charset=utf-8',
        ]);
    }
}
