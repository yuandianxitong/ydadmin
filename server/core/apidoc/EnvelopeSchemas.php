<?php

declare(strict_types=1);

namespace core\apidoc;

/**
 * 本仓库通用响应信封的 OpenAPI components.schemas 片段(响应契约见根 CLAUDE.md)。
 * 纯常量表,不持有任何状态:core/ 只新增只读静态方法,不新增可变静态属性。
 *
 * 只描述结构上已被契约保证的部分(spec 决策 4):Service 方法全部返回未强类型 array,
 * `data` 内部的业务字段没有任何可靠推导来源,因此一律标 `type: object` 且不写
 * `properties`——既不遗漏也不臆造。
 */
final class EnvelopeSchemas
{
    public const SUCCESS    = 'SuccessResponse';
    public const ERROR      = 'ErrorResponse';
    public const VALIDATION = 'ValidationErrorResponse';
    public const PAGINATED  = 'PaginatedResponse';

    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        $envelope = static fn (array $codeSchema, array $dataSchema): array => [
            'type'     => 'object',
            'required' => ['code', 'message', 'data', 'timestamp'],
            'properties' => [
                'code'      => $codeSchema,
                'message'   => ['type' => 'string'],
                'data'      => $dataSchema,
                'timestamp' => ['type' => 'integer', 'format' => 'int64', 'description' => 'Unix 时间戳(秒)'],
            ],
        ];

        return [
            self::SUCCESS => $envelope(
                ['type' => 'integer', 'example' => 200],
                ['type' => 'object', 'description' => '业务数据,字段随接口而定'],
            ),
            self::ERROR => $envelope(
                ['type' => 'integer', 'description' => '业务错误码;业务错误 HTTP 状态恒为 200,靠此字段区分', 'example' => 400],
                ['type' => 'object', 'description' => '业务数据,字段随接口而定'],
            ),
            self::VALIDATION => $envelope(
                ['type' => 'integer', 'example' => 422],
                [
                    'type'     => 'object',
                    'required' => ['errors'],
                    'properties' => [
                        'errors' => [
                            'type'                 => 'object',
                            'description'          => '字段名 => 第一条校验错误消息',
                            'additionalProperties' => ['type' => 'string'],
                        ],
                    ],
                ],
            ),
            self::PAGINATED => $envelope(
                ['type' => 'integer', 'example' => 200],
                [
                    'type'     => 'object',
                    'required' => ['list', 'pagination'],
                    'properties' => [
                        'list' => [
                            'type'        => 'array',
                            'items'       => ['type' => 'object'],
                            'description' => '列表元素结构随接口而定',
                        ],
                        'pagination' => [
                            'type'     => 'object',
                            'required' => ['current_page', 'per_page', 'total', 'last_page'],
                            'properties' => [
                                'current_page' => ['type' => 'integer'],
                                'per_page'     => ['type' => 'integer'],
                                'total'        => ['type' => 'integer'],
                                'last_page'    => ['type' => 'integer'],
                            ],
                        ],
                    ],
                ],
            ),
        ];
    }
}
