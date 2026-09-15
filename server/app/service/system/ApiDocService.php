<?php

declare(strict_types=1);

namespace app\service\system;

use app\middleware\AdminAuthMiddleware;
use app\middleware\ApiAuthMiddleware;
use core\apidoc\OpenApiDocument;
use core\apidoc\RouteHarvester;
use core\apidoc\RuleReflector;
use core\apidoc\RuleTranslator;
use core\base\Service;
use support\Container;

/**
 * API 文档编排（spec §5）。数据流：RouteHarvester 拿路由 → RuleReflector 从容器取控制器实例
 * 反射实调规则方法 → OpenApiDocument 组装。core/apidoc 不碰容器，解析器闭包由本类注入。
 */
class ApiDocService extends Service
{
    /** @var array<string, string> type => 路由前缀 */
    private const PREFIXES = [
        'admin' => '/adminapi',
        'api'   => '/api',
    ];

    /**
     * type => 代表「需登录」的中间件。core/ 不能写死 app\ 类名，由这里注入 RouteHarvester：
     * 路由实际挂了其中任一，文档才标需登录；否则是公开路由（security: []）。
     *
     * @var array<string, list<string>>
     */
    private const AUTH_MIDDLEWARE = [
        'admin' => [AdminAuthMiddleware::class],
        'api'   => [ApiAuthMiddleware::class],
    ];

    /**
     * 整份文档按 type 缓存：部署期固定，路由表与注解在运行期不变
     * （scripts/check-context-discipline.sh STATIC_WHITELIST 已登记）。
     *
     * @var array<string, array<string, mixed>>
     */
    private static array $documentCache = [];

    /**
     * type 归一的唯一入口，两个控制器动作与 document() 都经它，返回值必然是 PREFIXES 的键。
     * 缺省（null）与数组等非字符串 → 'admin'（管理端文档是本仓库唯一有内容的一份）；
     * 未知字符串 → 'api'（合法空文档，不把拼错的 type 猜成 admin）。
     */
    public function normalizeType(mixed $type): string
    {
        if (!is_string($type)) {
            return 'admin';
        }

        return array_key_exists($type, self::PREFIXES) ? $type : 'api';
    }

    /**
     * @param string $type 'admin' | 'api'；其它值按 normalizeType() 归一
     * @return array<string, mixed>
     */
    public function document(string $type): array
    {
        $type = $this->normalizeType($type);
        if (isset(self::$documentCache[$type])) {
            return self::$documentCache[$type];
        }

        $prefix = self::PREFIXES[$type];
        $harvester = new RouteHarvester($prefix, self::AUTH_MIDDLEWARE[$type]);
        $endpoints = $harvester->harvest();

        $reflector = new RuleReflector(static fn (string $class): object => Container::get($class));
        $rulesByOperationId = [];
        foreach ($endpoints as $endpoint) {
            $rulesByOperationId[$endpoint->operationId()] = $reflector->rulesFor($endpoint->controller, $endpoint->action);
        }

        $warnings = [...$harvester->skipped(), ...$reflector->warnings()];

        // serverUrl 必须是 '/'，不能是 $prefix：paths 的 key 已经带着完整前缀
        // （如 '/adminapi/system/dictionary/{id}'，Task 13 的路由表↔文档双射靠这一点），
        // OpenAPI 客户端按 server.url + path 拼请求 URL——传 $prefix 会把前缀拼两遍
        // （'/adminapi' + '/adminapi/...'），Swagger UI 的 Try it out 全部打到不存在的路径上。
        $title = $type === 'admin' ? lang('apidoc.admin_title') : lang('apidoc.api_title');
        $document = new OpenApiDocument(new RuleTranslator(), $title, (string) config('version.version'), '/');

        return self::$documentCache[$type] = $document->build($endpoints, $rulesByOperationId, $warnings);
    }
}
