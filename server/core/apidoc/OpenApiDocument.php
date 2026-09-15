<?php

declare(strict_types=1);

namespace core\apidoc;

/**
 * 把 EndpointDescriptor 列表 + 反射得到的规则 + 已收集的警告组装成一份合法的
 * OpenAPI 3.0 文档。判据：文档宁可少说，不可说谎。
 *
 * 鉴权(security / description / x-permission)由路由实际挂载的认证中间件
 * (EndpointDescriptor::$requiresAuth)与方法注解共同推导,只在 describePermission() 一处判断。
 *
 * 响应只声明真实存在的 HTTP 状态:本项目除未捕获异常(HTTP 500)外一律 HTTP 200,结果靠响应体
 * `code` 区分(校验失败 code 422、未登录 401、无权限 403 都是 HTTP 200)。200 的 schema 用 oneOf
 * 列出可能返回的信封:EndpointDescriptor 不携带「是否分页」,成功/错误/分页三种一律列出——
 * 不确定就不收窄;校验错误信封仅在该操作有规则时列出。
 *
 * 规则里的通配路径(`x.*`、`x.*.y`)折叠进父字段的 items / items.properties,不产出带点的字面字段名
 * (真实校验器不认那样的字段);每个 type: array 都带 items(OpenAPI 3.0.3 必需)。
 *
 * x-doc-warnings 汇总:调用方传入的 $warnings(RouteHarvester::skipped() + RuleReflector::warnings())、
 * 以及本类翻译每个字段规则时 RuleTranslator::translate() 返回的 notes(翻译不了的部分)。
 *
 * EndpointDescriptor::$path 保留路由表原始写法(如 `{id:\d+}`,pathParameters() 要靠
 * 这段类型约束才能判定 integer 还是 string),但 OpenAPI 3.0 的 paths 对象 key 只认
 * `{id}` 这种不带类型约束的写法——组装 paths 的 key 时用 normalizePathTemplate() 清洗。
 * normalizePathTemplate() 是公开静态纯函数:双射测试复用同一份实现(否则双真源)。
 */
final class OpenApiDocument
{
    private const OPENAPI_VERSION = '3.0.3';

    private const BEARER = [['bearerAuth' => []]];

    /** toJson() 里值为空 PHP 数组时必须编成 JSON 对象的键(它们在 OpenAPI 里语义上是对象)。 */
    private const OBJECT_KEYS = ['paths', 'properties', 'items', 'schema'];

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
                $parameters = [...$parameters, ...$this->queryParameterObjects($rules, $operationId, $collectedWarnings)];
            }

            [$permissionNode, $permissionDescription] = $this->describePermission($endpoint);

            $operation = [
                'operationId'  => $operationId,
                'tags'         => [$endpoint->tag],
                'summary'      => $operationId,
                'description'  => $permissionDescription,
                'x-permission' => $permissionNode,
                'security'     => $endpoint->requiresAuth ? self::BEARER : [],
                'responses'    => $this->buildResponses($endpoint, $rules !== null && $rules !== []),
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
            'security' => self::BEARER,
            'x-doc-warnings' => array_values($collectedWarnings),
        ];
    }

    /**
     * `build()` 对 PHP 调用方返回的是数组,空对象与空数组在数组里天然无法区分——但 json_encode()
     * 会把 PHP 空数组编成 JSON `[]`,而 OpenAPI 3.0 规定 `paths`、`properties`、`items`、`schema`
     * 以及 `properties` 下的每个字段 schema 语义上都是对象,空的必须编成 `{}`。这个转换只在编码
     * 这一步发生,`build()` 返回的数组不受影响。`security: []`(公开接口)、`tags` 等列表不受影响。
     *
     * 只此一份实现:调用方(如 ApiDocController)一律经这个方法编码。
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
     * @param array<int|string, mixed> $node
     * @return array<int|string, mixed>
     */
    private static function objectifyEmptyMaps(array $node): array
    {
        foreach ($node as $key => $value) {
            if (!is_array($value)) {
                continue;
            }
            if ($value === [] && in_array($key, self::OBJECT_KEYS, true)) {
                $node[$key] = new \stdClass();
                continue;
            }
            if ($key === 'properties') {
                // 字段名 => schema:字段名任意,不能按键名判断,每个空 schema 都编成 {}。
                foreach ($value as $field => $schema) {
                    $value[$field] = $schema === [] ? new \stdClass() : (is_array($schema) ? self::objectifyEmptyMaps($schema) : $schema);
                }
                $node[$key] = $value;
                continue;
            }
            $node[$key] = self::objectifyEmptyMaps($value);
        }

        return $node;
    }

    /**
     * OpenAPI 3.0 的 paths key 只认 `{name}`,不认路由表里的 `{name:正则}` 类型约束写法。
     * 公开静态纯函数:路由表↔文档双射测试复用这同一份实现,不能另写一份正则(双真源)。
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
     * 查询参数与请求体共用 foldRules():通配路径同样折叠,不输出带点的参数名。
     *
     * 数组/对象型查询参数:PHP 只认 `name[]=a&name[]=b` / `name[key]=v`,OpenAPI 3.0 的 style/explode
     * 没有任何一种能描述这种序列化(默认 form+explode 产出 `name=a&name=b`,PHP 只留最后一个值)。
     * 与其声明一个 PHP 解析不了的 style,不如保留 schema、用说明文字写明真实传法。
     *
     * @param array<string, string>|null $rules
     * @param list<string>                $warnings
     * @return list<array<string, mixed>>
     */
    private function queryParameterObjects(?array $rules, string $operationId, array &$warnings): array
    {
        $folded = $this->foldRules($rules, $operationId, '查询参数', $warnings);

        $parameters = [];
        foreach ($folded['properties'] as $name => $schema) {
            $parameter = [
                'name'     => $name,
                'in'       => 'query',
                'required' => in_array($name, $folded['required'], true),
                'schema'   => $schema,
            ];
            $type = $schema['type'] ?? null;
            if ($type === 'array' || $type === 'object') {
                $parameter['description'] = $type === 'array'
                    ? "数组参数,PHP 按 {$name}[]=a&{$name}[]=b 解析;OpenAPI 3.0 无法描述该序列化方式,Try it out 生成的查询串可能不被正确解析"
                    : "对象参数,PHP 按 {$name}[键]=值 解析;OpenAPI 3.0 无法描述该序列化方式,Try it out 生成的查询串可能不被正确解析";
            }
            $parameters[] = $parameter;
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
        $folded = $this->foldRules($rules, $operationId, '字段', $warnings);

        $bodySchema = ['type' => 'object', 'properties' => $folded['properties']];
        if ($folded['required'] !== []) {
            $bodySchema['required'] = $folded['required'];
        }

        return [
            'required' => $folded['required'] !== [],
            'content' => [
                'application/json' => ['schema' => $bodySchema],
            ],
        ];
    }

    /**
     * 逐条翻译规则并按点号路径折叠成嵌套 schema:
     *   `x`     → properties.x
     *   `x.*`   → properties.x.items(x 缺类型时补 type: array)
     *   `x.*.y` → properties.x.items.properties.y,y 的必填性进 x.items.required
     *   `x.y`   → properties.x.properties.y(x 缺类型时补 type: object)
     * 父字段没有自己的规则时建出父节点;父子规则谁先出现都得到同一结构。最后给每个 type: array
     * 补上缺失的 items(无通配子规则时为空 schema)。
     *
     * @param array<string, string>|null $rules
     * @param list<string>                $warnings
     * @return array{properties: array<string, array<string, mixed>>, required: list<string>}
     */
    private function foldRules(?array $rules, string $operationId, string $label, array &$warnings): array
    {
        $properties = [];
        $required = [];

        foreach ($rules ?? [] as $field => $ruleString) {
            $field = (string) $field;
            $translated = $this->translator->translate($ruleString);
            foreach ($translated['notes'] as $note) {
                $warnings[] = "{$operationId} {$label} {$field}:{$note}";
            }
            $schema = $this->applyNotes($translated['schema'], $translated['notes']);
            self::place($properties, $required, explode('.', $field), $schema, $translated['required']);
        }

        foreach ($properties as $name => $schema) {
            $properties[$name] = self::withArrayItems($schema);
        }

        return ['properties' => $properties, 'required' => $required];
    }

    /**
     * @param array<string, array<string, mixed>> $properties
     * @param list<string>                         $required
     * @param list<string>                         $segments 非空
     * @param array<string, mixed>                 $schema
     */
    private static function place(array &$properties, array &$required, array $segments, array $schema, bool $isRequired): void
    {
        $name = (string) array_shift($segments);

        if ($segments === []) {
            // 叶子:与此前由子规则隐式建出的节点合并,保留已有的 items / properties。
            $properties[$name] = array_merge($properties[$name] ?? [], $schema);
            if ($isRequired && !in_array($name, $required, true)) {
                $required[] = $name;
            }

            return;
        }

        $node = $properties[$name] ?? [];
        $child = (string) array_shift($segments);

        if ($child === '*') {
            $node['type'] ??= 'array';
            if ($segments === []) {
                // `x.*` 的规则就是元素 schema。元素级 required 在 OpenAPI 的 items 里无对应物,不写(少说不说谎)。
                $node['items'] = array_merge(is_array($node['items'] ?? null) ? $node['items'] : [], $schema);
            } else {
                $items = is_array($node['items'] ?? null) ? $node['items'] : [];
                $items['type'] ??= 'object';
                $node['items'] = self::placeInto($items, $segments, $schema, $isRequired);
            }
        } else {
            $node['type'] ??= 'object';
            $node = self::placeInto($node, [$child, ...$segments], $schema, $isRequired);
        }

        $properties[$name] = $node;
    }

    /**
     * 在一个对象 schema 的 properties / required 里放置剩余路径。
     *
     * @param array<string, mixed> $objectSchema
     * @param list<string>          $segments
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private static function placeInto(array $objectSchema, array $segments, array $schema, bool $isRequired): array
    {
        /** @var array<string, array<string, mixed>> $childProperties */
        $childProperties = is_array($objectSchema['properties'] ?? null) ? $objectSchema['properties'] : [];
        /** @var list<string> $childRequired */
        $childRequired = is_array($objectSchema['required'] ?? null) ? $objectSchema['required'] : [];

        self::place($childProperties, $childRequired, $segments, $schema, $isRequired);

        $objectSchema['properties'] = $childProperties;
        if ($childRequired !== []) {
            $objectSchema['required'] = $childRequired;
        }

        return $objectSchema;
    }

    /**
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private static function withArrayItems(array $schema): array
    {
        if (($schema['type'] ?? null) === 'array' && !array_key_exists('items', $schema)) {
            $schema['items'] = [];
        }
        if (is_array($schema['items'] ?? null)) {
            $schema['items'] = self::withArrayItems($schema['items']);
        }
        if (is_array($schema['properties'] ?? null)) {
            foreach ($schema['properties'] as $name => $child) {
                $schema['properties'][$name] = is_array($child) ? self::withArrayItems($child) : $child;
            }
        }

        return $schema;
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
     * 只声明真实存在的 HTTP 状态:200(一切业务结果,含校验失败/未登录/无权限)与 500(未捕获异常)。
     * 200 的描述只列该操作真实可能出现的业务 code。
     *
     * PHP 会把 '200' / '500' 这类数字字符串键自动转成 int,因此返回类型标 array<int, mixed>。
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildResponses(EndpointDescriptor $endpoint, bool $hasRules): array
    {
        $envelopes = [EnvelopeSchemas::SUCCESS, EnvelopeSchemas::ERROR, EnvelopeSchemas::PAGINATED];
        $codes = ['HTTP 状态恒为 200(未捕获异常除外),结果靠响应体 code 区分:code 200 为成功'];

        if ($hasRules) {
            $envelopes[] = EnvelopeSchemas::VALIDATION;
            $codes[] = 'code 422 为校验失败(data.errors 为「字段 => 消息」)';
        }
        if ($endpoint->requiresAuth) {
            $codes[] = 'code 401 为未登录或令牌无效/过期';
            if (!$endpoint->permissionSkipped) {
                $codes[] = 'code 403 为无权限';
            }
        }
        $codes[] = '其余非 200 的 code 为业务错误';

        return [
            '200' => [
                'description' => implode(';', $codes),
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'oneOf' => array_map(
                                static fn (string $name): array => ['$ref' => '#/components/schemas/' . $name],
                                $envelopes,
                            ),
                        ],
                    ],
                ],
            ],
            '500' => [
                'description' => '未捕获异常:HTTP 500,响应体仍为信封(code 500)',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/' . EnvelopeSchemas::ERROR],
                    ],
                ],
            ],
        ];
    }

    /**
     * 鉴权的唯一判断入口:x-permission(给机器)与 description(给人)来自同一次判断。
     * 顺序与运行期一致:公开路由不经认证与权限中间件;认证组内 #[PermissionSkip] 优先于
     * #[Permission](AdminPermissionMiddleware::resolve() 先查 Skip);两者皆无按默认拒绝。
     *
     * @return array{0: ?string, 1: string} [x-permission 的值, description]
     */
    private function describePermission(EndpointDescriptor $endpoint): array
    {
        if (!$endpoint->requiresAuth) {
            return [null, '公开接口，无需登录'];
        }
        if ($endpoint->permissionSkipped) {
            return [null, '需登录，无需权限节点'];
        }
        if ($endpoint->permission !== null) {
            return [$endpoint->permission, "需登录，且需权限 `{$endpoint->permission}`"];
        }

        return [null, '需登录；缺少权限注解，默认拒绝（仅超管可访问）'];
    }
}
