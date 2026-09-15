<?php

declare(strict_types=1);

namespace app\service\system;

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
     * 整份文档按 type 缓存：部署期固定，路由表与注解在运行期不变
     * （scripts/check-context-discipline.sh STATIC_WHITELIST 已登记）。
     *
     * @var array<string, array<string, mixed>>
     */
    private static array $documentCache = [];

    /**
     * @param string $type 'admin' | 'api'；未知值按 'api' 处理（返回空文档）
     * @return array<string, mixed>
     */
    public function document(string $type): array
    {
        $type = array_key_exists($type, self::PREFIXES) ? $type : 'api';
        if (isset(self::$documentCache[$type])) {
            return self::$documentCache[$type];
        }

        $prefix = self::PREFIXES[$type];
        $harvester = new RouteHarvester($prefix);
        $endpoints = $harvester->harvest();

        $reflector = new RuleReflector(static fn (string $class): object => Container::get($class));
        $rulesByOperationId = [];
        foreach ($endpoints as $endpoint) {
            $rulesByOperationId[$endpoint->operationId()] = $reflector->rulesFor($endpoint->controller, $endpoint->action);
        }

        $warnings = [...$harvester->skipped(), ...$reflector->warnings()];

        $title = $type === 'admin' ? lang('apidoc.admin_title') : lang('apidoc.api_title');
        $document = new OpenApiDocument(new RuleTranslator(), $title, (string) config('version.version'), $prefix);

        return self::$documentCache[$type] = $document->build($endpoints, $rulesByOperationId, $warnings);
    }
}
