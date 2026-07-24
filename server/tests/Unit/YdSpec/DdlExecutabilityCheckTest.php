<?php
// tests/Unit/YdSpec/DdlExecutabilityCheckTest.php
declare(strict_types=1);

namespace tests\Unit\YdSpec;

use core\ai\checks\CheckContext;
use core\ai\checks\DdlExecutabilityCheck;
use tests\TestCase;

class DdlExecutabilityCheckTest extends TestCase
{
    private function ctx(string $schemaPatch, array $entities): CheckContext
    {
        return new CheckContext('/tmp/none', ['files' => [], 'entities' => $entities], $entities, $schemaPatch, $schemaPatch, []);
    }

    public function testSkippedWhenNoPdo(): void
    {
        // 传入哨兵：构造时不解析框架连接，pdo 显式 false 表示“无连接”
        $check = new class(null, true) extends DdlExecutabilityCheck {
            public function __construct(?\PDO $pdo, private bool $forceNoDb) { parent::__construct($pdo); }
            protected function resolvePdo(): ?\PDO { return null; }
        };
        $ctx = $this->ctx("CREATE TABLE IF NOT EXISTS `widgets` (\n  `id` bigint unsigned NOT NULL AUTO_INCREMENT,\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB;", [['table' => 'widgets']]);
        $res = $check->check($ctx);
        $this->assertCount(1, $res);
        $this->assertSame('skipped', $res[0]->severity);
        $this->assertSame('ddl_executability', $res[0]->check);
    }

    public function testTablesFromSchemaParsesCreateStatements(): void
    {
        $check = new class extends DdlExecutabilityCheck {
            public function pub(string $s): array { return $this->tablesFromSchema($s); }
        };
        $sql = "CREATE TABLE IF NOT EXISTS `widgets` (\n  `id` bigint unsigned NOT NULL AUTO_INCREMENT,\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB;\n"
            . "CREATE TABLE gadgets (\n  id bigint unsigned NOT NULL AUTO_INCREMENT,\n  PRIMARY KEY (id)\n) ENGINE=InnoDB;";
        $this->assertSame(['widgets', 'gadgets'], $check->pub($sql));
    }
}
