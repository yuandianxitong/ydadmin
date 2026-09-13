<?php

declare(strict_types=1);

namespace core\generator;

/**
 * 一次生成请求的入参值对象（契约 §4.3/§4.4）。`overrides` 以列名为键，只携带前端
 * 可编辑的四个字段——`GeneratorService` 用它去覆盖实时查表得到的 ColumnDescriptor，
 * 其余字段一律以查表结果为准（客户端回传的不可信，见 spec §9.4）。
 */
final class GeneratorRequest
{
    /** @param array<string, array{form_type: string, searchable: bool, in_list: bool, in_form: bool}> $overrides */
    public function __construct(
        public readonly string $tableName,
        public readonly string $moduleName,
        public readonly string $modelName,
        public readonly string $tableComment,
        public readonly array $overrides,
    ) {
    }
}
