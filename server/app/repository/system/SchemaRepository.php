<?php

declare(strict_types=1);

namespace app\repository\system;

use support\Db;

/**
 * 数据库结构只读仓储：驱动代码生成器第一步（选表）与第二步（取字段）。
 *
 * 全仓库唯一一个不对应业务表的仓储：SHOW TABLE STATUS / SHOW FULL COLUMNS 面向整个数据库，
 * 没有单一 Eloquent 模型可挂，因此**不继承 core\base\Repository**——那个基类的 query() 以
 * $this->model 为前提，这里没有模型，也就没有 query() 可言。同样不受数据权限约束：数据权限
 * 管的是业务表的行级过滤，这里读的是 information_schema 级别的元数据，没有「行」的概念。
 *
 * 安全（spec §9.2）：SHOW FULL COLUMNS FROM `x` 的表名无法走参数绑定，因此 listColumns() /
 * tableExists() 一律先用 listTables() 的结果做逐字比对，命中才把表名拼进 SQL；调用方传来的
 * table 参数不会绕过这层比对直接进入 SQL 拼接。
 */
final class SchemaRepository
{
    /**
     * @return list<array{name: string, comment: string, engine: string, rows: int}>
     */
    public function listTables(): array
    {
        /** @var list<\stdClass> $rows */
        $rows = Db::select('SHOW TABLE STATUS');

        return array_map(static function (\stdClass $row): array {
            return [
                'name'    => (string) $row->Name,
                'comment' => (string) ($row->Comment ?? ''),
                'engine'  => (string) ($row->Engine ?? ''),
                'rows'    => (int) ($row->Rows ?? 0),
            ];
        }, $rows);
    }

    /**
     * 原始列信息（Field/Type/Null/Key/Default/Extra/Comment），交给 core\generator\TypeInference
     * 归一化。$table 不在 listTables() 白名单内时返回 []，不拼 SQL。
     *
     * @return list<array<string, mixed>>
     */
    public function listColumns(string $table): array
    {
        if (!$this->tableExists($table)) {
            return [];
        }

        // $table 此刻已确认逐字命中 listTables() 的真实表名，可安全拼进反引号内。
        /** @var list<\stdClass> $rows */
        $rows = Db::select("SHOW FULL COLUMNS FROM `{$table}`");

        return array_map(static fn (\stdClass $row): array => (array) $row, $rows);
    }

    public function tableExists(string $table): bool
    {
        foreach ($this->listTables() as $row) {
            if ($row['name'] === $table) {
                return true;
            }
        }

        return false;
    }
}
