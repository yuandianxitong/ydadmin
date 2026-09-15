<?php

declare(strict_types=1);

namespace core\apidoc;

/**
 * 把 EndpointDescriptor 列表 + 反射得到的规则 + 已收集的警告组装成一份合法的
 * OpenAPI 3.0 文档。权限节点(#[Permission]/#[PermissionSkip])写进 x-permission
 * (给机器)与 description 开头(给人)——TP8 从未做到这一点,看 Swagger 的人以前
 * 完全不知道调一个接口要什么权限。
 *
 * 响应 schema 一律用 oneOf 同时列出成功/错误/分页三种信封:EndpointDescriptor 不携带
 * 「是否分页」这个信息,与其臆造判断,不如把三种都列出来——不确定就不收窄。
 *
 * x-doc-warnings 汇总三个来源:调用方传入的 $warnings(RouteHarvester::skipped() +
 * RuleReflector::warnings(),接线在其它任务)、以及本类在翻译每个字段规则时,
 * RuleTranslator::translate() 返回的 notes(翻译不了的部分)。
 *
 * EndpointDescriptor::$path 保留路由表原始写法(如 `{id:\d+}`,pathParameters() 要靠
 * 这段类型约束才能判定 integer 还是 string),但 OpenAPI 3.0 的 paths 对象 key 只认
 * `{id}` 这种不带类型约束的写法——两处对同一个 $path 的要求互斥,只能各自处理:
 * pathParameters() 在原始串上判类型(Task 1 的职责),组装 paths 的 key 时本类
 * 用 normalizePathTemplate() 把类型约束清洗掉(本类的职责),互不依赖对方是否已经这么做。
 *
 * normalizePathTemplate() 是公开静态纯函数:Task 13 的「路由表 ↔ 文档双射」测试要
 * 拿它去规范化路由表那一侧再比对,必须复用同一份实现,不能自己另写一份正则
 * (否则清洗规则一改,测试会继续按旧规则绿着——双真源)。
 */
final class OpenApiDocument
{
    private const OPENAPI_VERSION = '3.0.3';

    public function __construct(
        private readonly RuleTranslator $translator,
        private readonly string $title,
        private readonly string $version,
        private readonly string $serverUrl,
    ) {
    }

    /**
     * @param list<EndpointDescriptor>                   $endpoints
     * @param array<string, array<string, string>|null>  $rulesByOperationId
     * @param list<string>                                $warnings
     * @return array<string, mixed>
     */
    public function build(array $endpoints, array $rulesByOperationId, array $warnings): array
    {
        $collectedWarnings = $warnings;
        $paths = [];

        foreach ($endpoints as $endpoint) {
            $operationId = $endpoint->operationId();
            $rules = $rulesByOperationId[$operationId] ?? null;

            $parameters = $this->pathParameterObjects($endpoint);
            if (!$endpoint->expectsBody()) {
                $parameters = [...$parameters, ...$this->queryParameterObjects($endpoint, $rules, $operationId, $collectedWarnings)];
            }

            [$permissionNode, $permissionDescription] = $this->describePermission($endpoint);

            $operation = [
                'operationId'  => $operationId,
                'tags'         => [$endpoint->tag],
                'summary'      => $operationId,
                'description'  => $permissionDescription,
                'x-permission' => $permissionNode,
                'responses'    => $this->buildResponses($endpoint),
            ];
            if ($parameters !== []) {
                $operation['parameters'] = $parameters;
            }
            if ($endpoint->expectsBody()) {
                $operation['requestBody'] = $this->buildRequestBody($rules, $operationId, $collectedWarnings);
            }

            $paths[self::normalizePathTemplate($endpoint->path)][strtolower($endpoint->method)] = $operation;
        }

        return [
            'openapi' => self::OPENAPI_VERSION,
            'info' => [
                'title'   => $this->title,
                'version' => $this->version,
            ],
            'servers' => [['url' => $this->serverUrl]],
            'paths' => $paths,
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => ['type' => 'http', 'scheme' => 'bearer', 'bearerFormat' => 'JWT'],
                ],
                'schemas' => EnvelopeSchemas::all(),
            ],
            'security' => [['bearerAuth' => []]],
            'x-doc-warnings' => array_values($collectedWarnings),
        ];
    }

    /**
     * `build()` 对 PHP 调用方返回的是数组,空对象与空数组在数组里天然无法区分,这在 PHP 里
     * 不是问题——但 json_encode() 会把 PHP 空数组编成 JSON `[]`,而 OpenAPI 3.0 规定
     * `paths`、schema 的 `properties` 语义上都是对象,空的必须编成 `{}`,不能是 `[]`
     * (Swagger UI/校验器按对象解析,拿到数组会直接判非法文档)。这个转换只在编码成 JSON
     * 字符串这一步发生,`build()` 返回给 PHP 调用方的数组不受影响,调用方仍按数组读
     * `$doc['paths']`(Task 13 的双射/黄金测试依赖这一点)。
     *
     * 只此一份实现:调用方(如 ApiDocController)一律经这个方法编码,不各自写一遍同样的
     * "空数组转 stdClass" 逻辑,否则判定规则一改,遗漏的调用点会继续把非法文档发出去。
     *
     * @param array<string, mixed> $document build() 的返回值
     */
    public static function toJson(array $document): string
    {
        return (string) json_encode(
            self::objectifyEmptyMaps($document),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );
    }

    /**
     * 递归地把 `paths` 与任意层级的 `properties` 键,只要值是空 PHP 数组,换成 `stdClass`,
     * 这样 json_encode() 才会编成 `{}`。其它键(包括 `tags`、`parameters`、
     * `x-doc-warnings` 这类语义上是列表的空数组)不受影响,继续编成 `[]`。
     *
     * @param array<int|string, mixed> $node
     * @return array<int|string, mixed>
     */
    private static function objectifyEmptyMaps(array $node): array
    {
        foreach ($node as $key => $value) {
            if (($key === 'paths' || $key === 'properties') && $value === []) {
                $node[$key] = new \stdClass();
                continue;
            }
            if (is_array($value)) {
                $node[$key] = self::objectifyEmptyMaps($value);
            }
        }

        return $node;
    }

    /**
     * OpenAPI 3.0 的 paths key 只认 `{name}`,不认路由表里的 `{name:正则}` 类型约束写法
     * (webman 路由用后者拿参数类型,EndpointDescriptor::pathParameters() 也靠它判
     * integer/string——见类注释)。公开静态纯函数:Task 13 的路由表↔文档双射测试要复用
     * 这同一份实现去规范化路由表那一侧,不能另写一份正则(双真源)。
     */
    public static function normalizePathTemplate(string $rawPath): string
    {
        return (string) preg_replace('/\{([A-Za-z_][A-Za-z0-9_]*):[^}]+\}/', '{$1}', $rawPath);
    }

    /** @return list<array<string, mixed>> */
    private function pathParameterObjects(EndpointDescriptor $endpoint): array
    {
        $parameters = [];
        foreach ($endpoint->pathParameters() as $pathParameter) {
            $parameters[] = [
                'name'     => $pathParameter['name'],
                'in'       => 'path',
                'required' => true,
                'schema'   => ['type' => $pathParameter['type']],
            ];
        }

        return $parameters;
    }

    /**
     * @param array<string, string>|null $rules
     * @param list<string>                $warnings
     * @return list<array<string, mixed>>
     */
    private function queryParameterObjects(EndpointDescriptor $endpoint, ?array $rules, string $operationId, array &$warnings): array
    {
        if ($rules === null) {
            return [];
        }

        $parameters = [];
        foreach ($rules as $field => $ruleString) {
            $translated = $this->translator->translate($ruleString);
            $schema = $this->applyNotes($translated['schema'], $translated['notes']);
            foreach ($translated['notes'] as $note) {
                $warnings[] = "{$operationId} 查询参数 {$field}:{$note}";
            }
            $parameters[] = [
                'name'     => (string) $field,
                'in'       => 'query',
                'required' => $translated['required'],
                'schema'   => $schema,
            ];
        }

        return $parameters;
    }

    /**
     * @param array<string, string>|null $rules
     * @param list<string>                $warnings
     * @return array<string, mixed>
     */
    private function buildRequestBody(?array $rules, string $operationId, array &$warnings): array
    {
        $properties = [];
        $required = [];

        foreach ($rules ?? [] as $field => $ruleString) {
            $translated = $this->translator->translate($ruleString);
            $properties[(string) $field] = $this->applyNotes($translated['schema'], $translated['notes']);
            foreach ($translated['notes'] as $note) {
                $warnings[] = "{$operationId} 字段 {$field}:{$note}";
            }
            if ($translated['required']) {
                $required[] = (string) $field;
            }
        }

        $bodySchema = ['type' => 'object', 'properties' => $properties];
        if ($required !== []) {
            $bodySchema['required'] = $required;
        }

        return [
            'required' => $required !== [],
            'content' => [
                'application/json' => ['schema' => $bodySchema],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $schema
     * @param list<string>          $notes
     * @return array<string, mixed>
     */
    private function applyNotes(array $schema, array $notes): array
    {
        if ($notes === []) {
            return $schema;
        }

        $existing = is_string($schema['description'] ?? null) ? $schema['description'] . ' ' : '';
        $schema['description'] = trim($existing . implode(';', $notes));

        return $schema;
    }

    /**
     * PHP 会把 '200' / '422' 这类数字字符串键自动转成 int,因此返回类型标 array<int, mixed>,
     * 而不是看起来更直观的 array<string, mixed>(那样标会跟 PHPStan 推断的真实类型对不上)。
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildResponses(EndpointDescriptor $endpoint): array
    {
        $responses = [
            '200' => [
                'description' => '业务成功或业务错误(业务错误 HTTP 状态仍为 200,靠 code 字段区分)',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'oneOf' => [
                                ['$ref' => '#/components/schemas/' . EnvelopeSchemas::SUCCESS],
                                ['$ref' => '#/components/schemas/' . EnvelopeSchemas::ERROR],
                                ['$ref' => '#/components/schemas/' . EnvelopeSchemas::PAGINATED],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        if ($endpoint->expectsBody()) {
            $responses['422'] = [
                'description' => '校验失败',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/' . EnvelopeSchemas::VALIDATION],
                    ],
                ],
            ];
        }

        return $responses;
    }

    /**
     * 权限节点的唯一判断入口:x-permission(给机器)与 description 开头(给人)必须
     * 来自同一次判断算出的同一组值,不能分别独立推导,否则未来两者可能分叉。
     *
     * @return array{0: ?string, 1: string} [x-permission 的值, description 前缀]
     */
    private function describePermission(EndpointDescriptor $endpoint): array
    {
        if ($endpoint->permissionSkipped) {
            return [null, '[免鉴权]'];
        }
        if ($endpoint->permission !== null) {
            return [$endpoint->permission, "[权限: {$endpoint->permission}]"];
        }

        return [null, '[权限未标注:既未 #[Permission] 也未 #[PermissionSkip],中间件按默认拒绝(403)处理]'];
    }
}
