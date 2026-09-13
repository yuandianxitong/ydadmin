<?php

declare(strict_types=1);

namespace core\generator;

/**
 * 表 + 列的值对象（spec §5.2）。由 `SchemaRepository::listColumns()` 的原始结果经
 * `TypeInference::describe()` 逐列构建，供 `ModuleBlueprint` 与各模板消费。
 */
final class TableDefinition
{
    /** @param list<ColumnDescriptor> $columns */
    public function __construct(
        public readonly string $name,
        public readonly string $comment,
        public readonly array $columns,
    ) {
    }

    public function column(string $name): ?ColumnDescriptor
    {
        foreach ($this->columns as $column) {
            if ($column->name === $name) {
                return $column;
            }
        }

        return null;
    }

    public function has(string $name): bool
    {
        return $this->column($name) !== null;
    }

    public function hasStatus(): bool
    {
        return $this->has('status');
    }

    public function hasSoftDeletes(): bool
    {
        return $this->has('deleted_at');
    }

    /** spec §7.3：有 created_by 列才置 $dataScoped，否则显式 null（照 FileRepository 的先例）。 */
    public function creatorColumn(): ?string
    {
        return $this->has('created_by') ? 'created_by' : null;
    }

    /** spec §7.3：dept_id 与 created_by 叠加使用，任一存在都独立判定。 */
    public function deptColumn(): ?string
    {
        return $this->has('dept_id') ? 'dept_id' : null;
    }

    /** @return list<string> 带 UNIQUE 索引（Key === 'UNI'）的列名，不含主键。 */
    public function uniqueColumns(): array
    {
        $names = [];
        foreach ($this->columns as $column) {
            if ($column->key === 'UNI') {
                $names[] = $column->name;
            }
        }

        return $names;
    }

    /** 取 Key === 'PRI' 的列名；没有则默认 'id'（生成器面对的表理论上总有主键，这只是保底）。 */
    public function primaryKey(): string
    {
        foreach ($this->columns as $column) {
            if ($column->key === 'PRI') {
                return $column->name;
            }
        }

        return 'id';
    }
}
