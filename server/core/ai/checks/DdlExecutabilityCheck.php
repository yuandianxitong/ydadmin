<?php
declare(strict_types=1);

namespace core\ai\checks;

use core\database\SqlRunner;
use think\facade\Db;

/**
 * DDL 可执行性：用一次性前缀真建后删，验证 CREATE TABLE 可建。无 DB 连接 → skipped。
 */
class DdlExecutabilityCheck implements CheckInterface
{
    public function __construct(private ?\PDO $pdo = null)
    {
    }

    public function name(): string
    {
        return 'ddl_executability';
    }

    public function check(CheckContext $ctx): array
    {
        $pdo = $this->pdo ?? $this->resolvePdo();
        if ($pdo === null) {
            return [new CheckResult($this->name(), 'skipped', '无数据库连接，跳过 DDL 可执行性检查')];
        }

        $prefix = 'ydchk_' . bin2hex(random_bytes(4)) . '_';
        $runner = new SqlRunner($pdo, $prefix);
        $tables = $this->tablesFromSchema($ctx->schemaPatch);

        $results = [];
        try {
            $runner->runSql($ctx->schemaPatch);
        } catch (\Throwable $e) {
            $results[] = new CheckResult($this->name(), 'error', 'DDL 执行失败：' . $e->getMessage());
        } finally {
            $drop = '';
            foreach ($tables as $t) {
                $drop .= "DROP TABLE IF EXISTS `{$t}`;\n";
            }
            if ($drop !== '') {
                try {
                    $runner->runSql($drop);
                } catch (\Throwable $e) {
                    $results[] = new CheckResult($this->name(), 'error', '清理临时表失败：' . $e->getMessage());
                }
            }
        }
        return $results;
    }

    protected function resolvePdo(): ?\PDO
    {
        try {
            return Db::connect()->getPdo();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * 从 schema SQL 中解析所有 CREATE TABLE 的裸表名（去重、保序），
     * 以保证清理阶段精确覆盖本次实际创建的表。
     *
     * @return array<int,string>
     */
    protected function tablesFromSchema(string $sql): array
    {
        $tables = [];
        if (preg_match_all(
            '/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(?P<name>[a-zA-Z0-9_]+)`?/i',
            $sql,
            $matches
        )) {
            foreach ($matches['name'] as $name) {
                if ($name !== '' && !in_array($name, $tables, true)) {
                    $tables[] = $name;
                }
            }
        }
        return $tables;
    }
}
