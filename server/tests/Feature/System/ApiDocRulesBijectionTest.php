<?php

declare(strict_types=1);

namespace tests\Feature\System;

use app\service\system\ApiDocService;
use core\apidoc\OpenApiDocument;
use core\apidoc\RouteHarvester;
use core\apidoc\RuleReflector;
use ReflectionProperty;
use support\Container;
use tests\Support\ApiTestCase;

/** spec §10.2：每个 {action}Rules() 里的字段都必须出现在该端点的 parameters 或 requestBody 里。 */
final class ApiDocRulesBijectionTest extends ApiTestCase
{
    public function test_every_rules_field_is_documented(): void
    {
        self::ensureRoutesLoaded();

        // 与 ApiDocBijectionTest 同理：别的测试可能用反射清空过 ApiDocService::$documentCache
        // 却不恢复，本测试要看到当前状态下新鲜 build 出来的文档，不依赖执行顺序。
        $cacheProperty = new ReflectionProperty(ApiDocService::class, 'documentCache');
        $cacheProperty->setAccessible(true);
        $cacheProperty->setValue(null, []);

        $harvester = new RouteHarvester('/adminapi');
        $reflector = new RuleReflector(static fn (string $class): object => Container::get($class));
        $document = Container::get(ApiDocService::class)->document('admin');

        $checked = 0;
        foreach ($harvester->harvest() as $endpoint) {
            $rules = $reflector->rulesFor($endpoint->controller, $endpoint->action);
            if ($rules === null || $rules === []) {
                continue;
            }
            $checked++;

            // 同 ApiDocBijectionTest：复用 OpenApiDocument 的清洗逻辑，不在这里另写一份正则。
            $path = OpenApiDocument::normalizePathTemplate($endpoint->path);
            $operation = $document['paths'][$path][strtolower($endpoint->method)] ?? null;
            $this->assertIsArray($operation, "文档缺少 {$endpoint->operationId()} 对应的 operation（{$endpoint->method} {$path}）");

            $roots = [];
            foreach ($operation['parameters'] ?? [] as $parameter) {
                if (($parameter['in'] ?? null) === 'query') {
                    $roots[$parameter['name']] = $parameter['schema'];
                }
            }
            foreach ($operation['requestBody']['content']['application/json']['schema']['properties'] ?? [] as $name => $schema) {
                $roots[$name] = $schema;
            }

            foreach (array_keys($rules) as $field) {
                $this->assertTrue(
                    self::resolves((string) $field, $roots),
                    "{$endpoint->operationId()} 的规则字段 '{$field}' 没有出现在文档的 parameters 或 requestBody 里"
                );
            }
        }

        $this->assertGreaterThanOrEqual(38, $checked, 'spec §6 盘点：38 处有校验的调用点，一个都不能在遍历里被跳过');
    }

    /**
     * 按折叠语义解析规则字段：`a` 是顶层参数/属性；`a.*` 是 `a.items`；`a.*.b` 是
     * `a.items.properties.b`；`a.b` 是 `a.properties.b`。不再要求文档里有名叫 "a.*.b" 的字面键
     * （那样的字段真实校验器根本不认，文档在说谎）。
     *
     * @param array<string, mixed> $roots
     */
    private static function resolves(string $field, array $roots): bool
    {
        $segments = explode('.', $field);
        $head = array_shift($segments);
        if (!is_array($roots[$head] ?? null)) {
            return false;
        }
        $node = $roots[$head];
        foreach ($segments as $segment) {
            $next = $segment === '*' ? ($node['items'] ?? null) : ($node['properties'][$segment] ?? null);
            if (!is_array($next)) {
                return false;
            }
            $node = $next;
        }

        return true;
    }
}
