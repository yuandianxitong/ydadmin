<?php

declare(strict_types=1);

namespace core\generator;

/**
 * 单列的值对象，含 TypeInference 的推断结果（spec §5.2、§4.2）。
 * 纯数据容器，无状态、无外部依赖；`core/` 不得依赖 `app/`。
 */
final class ColumnDescriptor
{
    /**
     * @param string $type 归一化类型：string|integer|decimal|boolean|text|date|datetime|enum|json
     * @param string $rawType 原始 MySQL 类型，如 varchar(200)
     * @param string $key PRI|UNI|MUL|''
     * @param string $extra auto_increment|''
     * @param string $formType input|textarea|number|switch|select|datepicker|image
     * @param list<string> $enumValues
     */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly string $rawType,
        public readonly bool $nullable,
        public readonly ?string $default,
        public readonly string $comment,
        public readonly string $key,
        public readonly string $extra,
        public readonly string $formType,
        public readonly bool $searchable,
        public readonly bool $inList,
        public readonly bool $inForm,
        public readonly array $enumValues,
    ) {
    }

    /**
     * 契约 §4.2 的十二个字段，不含 enumValues（前端不消费它）。
     *
     * @return array{name: string, type: string, raw_type: string, nullable: bool, default: ?string, comment: string, key: string, extra: string, form_type: string, searchable: bool, in_list: bool, in_form: bool}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'raw_type' => $this->rawType,
            'nullable' => $this->nullable,
            'default' => $this->default,
            'comment' => $this->comment,
            'key' => $this->key,
            'extra' => $this->extra,
            'form_type' => $this->formType,
            'searchable' => $this->searchable,
            'in_list' => $this->inList,
            'in_form' => $this->inForm,
        ];
    }

    /**
     * 只替换前端可编辑的四个字段（契约 §4.3：客户端回传只采信这四个），其余八个字段原样保留。
     */
    public function withOverrides(string $formType, bool $searchable, bool $inList, bool $inForm): self
    {
        return new self(
            $this->name,
            $this->type,
            $this->rawType,
            $this->nullable,
            $this->default,
            $this->comment,
            $this->key,
            $this->extra,
            $formType,
            $searchable,
            $inList,
            $inForm,
            $this->enumValues,
        );
    }
}
