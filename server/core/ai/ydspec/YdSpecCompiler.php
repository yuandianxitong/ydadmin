<?php
declare(strict_types=1);

namespace core\ai\ydspec;

/**
 * YdSpec 编译器（纯函数，无副作用）。
 * 把 ydspec/v1 数组编译成：
 *  - 每实体的 CREATE TABLE DDL（schema.sql 幂等风格、裸表名，前缀由 SqlRunner 运行时套用）
 *  - 每实体的列描述符（形状同 CodeGeneratorService::getTableColumns()，喂给 generate() 绕过 live DB）
 */
class YdSpecCompiler
{
    public function compile(array $spec): array
    {
        $moduleName  = (string) ($spec['module']['name'] ?? '');
        $moduleTitle = (string) ($spec['module']['title'] ?? $moduleName);
        $entities    = $spec['entities'] ?? [];
        $mainName    = $this->mainEntityName($entities, $moduleName);

        $compiled = [];
        $ddls = [];
        foreach ($entities as $entity) {
            $ddl = $this->entityDdl($entity, $moduleTitle);
            $ddls[] = $ddl;
            $columns = $this->entityColumns($entity);
            $compiled[] = [
                'name'              => (string) $entity['name'],
                'table'             => (string) $entity['table'],
                'module'            => $moduleName,
                'model'             => (string) $entity['name'],
                'route_group'       => $this->routeGroup($entity, $mainName, $moduleName),
                'is_main'           => ((string) $entity['name']) === $mainName,
                'has_status_switch' => $this->hasStatusSwitch($columns),
                'comment'           => $moduleTitle,
                'ddl'               => $ddl,
                'columns'           => $columns,
            ];
        }

        $schemaPatch = implode("\n\n", $ddls) . "\n";
        return [
            'schema_patch' => $schemaPatch,
            'update_sql'   => $schemaPatch,
            'entities'     => $compiled,
        ];
    }

    // ---------------- DDL ----------------

    public function entityDdl(array $entity, string $moduleTitle): string
    {
        $table = (string) $entity['table'];
        $kind  = (string) ($entity['kind'] ?? 'business');
        $soft  = (string) ($entity['soft_delete'] ?? 'none');

        $lines = ['  `id` bigint unsigned NOT NULL AUTO_INCREMENT'];
        foreach ($entity['fields'] ?? [] as $field) {
            $lines[] = '  ' . $this->columnDdl($field);
        }
        $lines[] = '  `created_at` timestamp NULL DEFAULT NULL';
        if ($kind !== 'log') {
            $lines[] = '  `updated_at` timestamp NULL DEFAULT NULL';
        }
        if ($soft === 'soft') {
            $lines[] = '  `deleted_at` timestamp NULL DEFAULT NULL';
        }

        $keys = ['  PRIMARY KEY (`id`)'];
        foreach ($this->collectIndexes($entity) as $idx) {
            $cols    = implode('`,`', $idx['fields']);
            $suffix  = $idx['unique'] ? 'unique' : 'index';
            $keyword = $idx['unique'] ? 'UNIQUE KEY' : 'KEY';
            $name    = $table . '_' . implode('_', $idx['fields']) . '_' . $suffix;
            $keys[]  = "  {$keyword} `{$name}` (`{$cols}`)";
        }

        $body    = implode(",\n", array_merge($lines, $keys));
        $comment = str_replace("'", "''", $moduleTitle);
        return "CREATE TABLE IF NOT EXISTS `{$table}` (\n{$body}\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='{$comment}';";
    }

    private function columnDdl(array $field): string
    {
        $name = (string) $field['name'];
        $sql  = "`{$name}` " . $this->mysqlType($field) . ' ' . (!empty($field['nullable']) ? 'NULL' : 'NOT NULL');
        if (array_key_exists('default', $field) && $field['default'] !== null) {
            $sql .= ' DEFAULT ' . $this->defaultLiteral($field);
        }
        if (($field['type'] ?? '') === 'enum') {
            $sql .= " COMMENT '可选值:" . implode(',', $field['enum'] ?? []) . "'";
        }
        return $sql;
    }

    private function mysqlType(array $field): string
    {
        return match ((string) ($field['type'] ?? 'string')) {
            'string'   => 'varchar(' . (int) ($field['length'] ?? 255) . ')',
            'text'     => 'text',
            'int'      => 'int',
            'bigint'   => 'bigint',
            'decimal'  => 'decimal(' . (int) ($field['precision'] ?? 10) . ',' . (int) ($field['scale'] ?? 2) . ')',
            'boolean'  => 'tinyint(1)',
            'datetime' => 'datetime',
            'date'     => 'date',
            'enum'     => 'varchar(' . $this->enumLength($field) . ')',
            'json'     => 'json',
            default    => 'varchar(255)',
        };
    }

    private function enumLength(array $field): int
    {
        if (!empty($field['length'])) {
            return (int) $field['length'];
        }
        $max = 0;
        foreach ($field['enum'] ?? [] as $v) {
            $max = max($max, strlen((string) $v));
        }
        return max(32, $max);
    }

    private function defaultLiteral(array $field): string
    {
        $d = $field['default'];
        if (in_array((string) ($field['type'] ?? ''), ['int', 'bigint', 'decimal', 'boolean'], true)) {
            return is_bool($d) ? ($d ? '1' : '0') : (string) $d;
        }
        return "'" . str_replace("'", "''", (string) $d) . "'";
    }

    /** @return array<int,array{fields:array<int,string>,unique:bool}> 去重后的索引清单（按列集合去重，unique 吞并 index） */
    private function collectIndexes(array $entity): array
    {
        $order  = [];
        $fields = [];
        $unique = [];
        $add = function (array $cols, bool $isUnique) use (&$order, &$fields, &$unique): void {
            $key = implode(',', $cols);
            if (!isset($fields[$key])) {
                $order[] = $key;
                $fields[$key] = $cols;
                $unique[$key] = false;
            }
            if ($isUnique) {
                $unique[$key] = true;
            }
        };
        foreach ($entity['fields'] ?? [] as $field) {
            if (!empty($field['unique'])) {
                $add([(string) $field['name']], true);
            } elseif (!empty($field['index'])) {
                $add([(string) $field['name']], false);
            }
        }
        foreach ($entity['indexes'] ?? [] as $idx) {
            $add(array_map('strval', $idx['fields'] ?? []), ($idx['type'] ?? 'index') === 'unique');
        }
        $result = [];
        foreach ($order as $key) {
            $result[] = ['fields' => $fields[$key], 'unique' => $unique[$key]];
        }
        return $result;
    }

    // ---------------- 列描述符 ----------------

    public function entityColumns(array $entity): array
    {
        $kind = (string) ($entity['kind'] ?? 'business');
        $soft = (string) ($entity['soft_delete'] ?? 'none');

        $cols = [$this->descriptor('id', 'bigint', 'bigint', false, null, 'ID', 'PRI', 'auto_increment')];
        foreach ($entity['fields'] ?? [] as $field) {
            $cols[] = $this->fieldDescriptor($field);
        }
        $cols[] = $this->descriptor('created_at', 'timestamp', 'timestamp', true, null, '创建时间', '', '');
        if ($kind !== 'log') {
            $cols[] = $this->descriptor('updated_at', 'timestamp', 'timestamp', true, null, '更新时间', '', '');
        }
        if ($soft === 'soft') {
            $cols[] = $this->descriptor('deleted_at', 'timestamp', 'timestamp', true, null, '删除时间', '', '');
        }
        return $cols;
    }

    private function fieldDescriptor(array $field): array
    {
        $name    = (string) $field['name'];
        $isEnum  = ($field['type'] ?? '') === 'enum';
        $key     = !empty($field['unique']) ? 'UNI' : (!empty($field['index']) ? 'MUL' : '');
        $default = array_key_exists('default', $field) ? $field['default'] : null;

        $desc = $this->descriptor(
            $name,
            $this->baseType((string) ($field['type'] ?? 'string')),
            $this->mysqlType($field),
            !empty($field['nullable']),
            $default,
            $name,
            $key,
            ''
        );
        if ($isEnum) {
            $desc['is_enum']     = true;
            $desc['enum_values'] = array_map('strval', $field['enum'] ?? []);
            $desc['form_type']   = 'select';
        }
        return $desc;
    }

    private function baseType(string $ydType): string
    {
        return match ($ydType) {
            'string', 'enum' => 'varchar',
            'boolean'        => 'tinyint',
            default          => $ydType,
        };
    }

    private function descriptor(string $name, string $type, string $rawType, bool $nullable, $default, string $comment, string $key, string $extra): array
    {
        return [
            'name'       => $name,
            'type'       => $type,
            'raw_type'   => $rawType,
            'nullable'   => $nullable,
            'default'    => $default,
            'comment'    => $comment,
            'key'        => $key,
            'extra'      => $extra,
            'form_type'  => $this->formType($name, $type, $rawType),
            'searchable' => $this->searchable($name),
            'in_list'    => $this->inList($name),
            'in_form'    => $this->inForm($name),
        ];
    }

    private function formType(string $field, string $type, string $rawType): string
    {
        if (in_array($field, ['status', 'is_system', 'is_default', 'is_show', 'is_visible'], true)) {
            return 'switch';
        }
        if (in_array($field, ['sort', 'order', 'level', 'weight'], true)) {
            return 'number';
        }
        if (str_contains($rawType, 'text')) {
            return 'textarea';
        }
        if (in_array($type, ['decimal', 'int', 'bigint', 'tinyint'], true)) {
            return 'number';
        }
        if (in_array($type, ['datetime', 'date', 'timestamp'], true)) {
            return 'datepicker';
        }
        return 'input';
    }

    private function searchable(string $field): bool
    {
        return in_array($field, ['name', 'title', 'username', 'email', 'phone', 'mobile', 'code', 'keyword', 'label', 'nickname'], true)
            || $field === 'status';
    }

    private function inList(string $field): bool
    {
        return !in_array($field, ['deleted_at', 'updated_at', 'password', 'remember_token', 'content'], true);
    }

    private function inForm(string $field): bool
    {
        return !in_array($field, ['id', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by', 'login_count', 'last_login_ip', 'last_login_time'], true);
    }

    private function hasStatusSwitch(array $columns): bool
    {
        foreach ($columns as $col) {
            if (($col['name'] ?? '') === 'status') {
                return empty($col['is_enum']);
            }
        }
        return false;
    }

    // ---------------- 命名/路由 ----------------

    private function mainEntityName(array $entities, string $moduleName): string
    {
        foreach ($entities as $e) {
            if ($this->slug((string) ($e['name'] ?? '')) === $moduleName) {
                return (string) $e['name'];
            }
        }
        return (string) ($entities[0]['name'] ?? '');
    }

    private function routeGroup(array $entity, string $mainName, string $moduleName): string
    {
        $name = (string) ($entity['name'] ?? '');
        if ($name === $mainName) {
            return $moduleName;
        }
        $remainder = ($mainName !== '' && str_starts_with($name, $mainName)) ? substr($name, strlen($mainName)) : $name;
        return $moduleName . '-' . $this->kebab($remainder !== '' ? $remainder : $name);
    }

    private function slug(string $name): string
    {
        return strtolower((string) preg_replace('/[^A-Za-z0-9]/', '', $name));
    }

    private function kebab(string $name): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $name));
    }
}
