<?php
// tests/Unit/YdSpec/YdSpecCompilerTest.php
declare(strict_types=1);

namespace tests\Unit\YdSpec;

use core\ai\ydspec\YdSpecCompiler;
use tests\TestCase;

class YdSpecCompilerTest extends TestCase
{
    private function spec(string $name): array
    {
        return json_decode((string) file_get_contents(dirname(__DIR__, 2) . "/fixtures/ydspec/{$name}.json"), true);
    }

    public function testAppointmentDdlGolden(): void
    {
        $spec = $this->spec('appointment');
        $ddl = (new YdSpecCompiler())->entityDdl($spec['entities'][0], $spec['module']['title']);
        $expected = <<<'SQL'
CREATE TABLE IF NOT EXISTS `appointments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `appointment_no` varchar(64) NOT NULL,
  `user_id` bigint NOT NULL,
  `start_at` datetime NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'pending' COMMENT '可选值:pending,confirmed,completed,cancelled',
  `paid_amount` decimal(12,2) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `appointments_appointment_no_unique` (`appointment_no`),
  KEY `appointments_user_id_index` (`user_id`),
  KEY `appointments_user_id_start_at_index` (`user_id`,`start_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='预约管理';
SQL;
        $this->assertSame($expected, $ddl);
    }

    public function testLogTableHasNoUpdatedOrDeletedAt(): void
    {
        $spec = $this->spec('points_log');
        $ddl = (new YdSpecCompiler())->entityDdl($spec['entities'][0], $spec['module']['title']);
        $expected = <<<'SQL'
CREATE TABLE IF NOT EXISTS `points_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint NOT NULL,
  `points_change` int NOT NULL,
  `reason` varchar(200) NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `points_logs_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='积分流水';
SQL;
        $this->assertSame($expected, $ddl);
    }

    public function testEnumColumnDescriptor(): void
    {
        $spec = $this->spec('article');
        $cols = (new YdSpecCompiler())->entityColumns($spec['entities'][0]);
        $status = null;
        foreach ($cols as $c) {
            if ($c['name'] === 'status') { $status = $c; }
        }
        $this->assertNotNull($status);
        $this->assertTrue($status['is_enum']);
        $this->assertSame(['draft', 'published'], $status['enum_values']);
        $this->assertSame('varchar', $status['type']);
        $this->assertSame('select', $status['form_type']);
    }

    public function testReservedColumnsInjected(): void
    {
        $spec = $this->spec('appointment');
        $names = array_column((new YdSpecCompiler())->entityColumns($spec['entities'][0]), 'name');
        $this->assertSame('id', $names[0]);
        $this->assertContains('created_at', $names);
        $this->assertContains('updated_at', $names);
        $this->assertContains('deleted_at', $names);
    }

    public function testLogEntityColumnsOmitUpdatedDeleted(): void
    {
        $spec = $this->spec('points_log');
        $names = array_column((new YdSpecCompiler())->entityColumns($spec['entities'][0]), 'name');
        $this->assertContains('created_at', $names);
        $this->assertNotContains('updated_at', $names);
        $this->assertNotContains('deleted_at', $names);
    }

    public function testDecimalRawType(): void
    {
        $spec = $this->spec('appointment');
        $cols = (new YdSpecCompiler())->entityColumns($spec['entities'][0]);
        $paid = null;
        foreach ($cols as $c) {
            if ($c['name'] === 'paid_amount') { $paid = $c; }
        }
        $this->assertSame('decimal(12,2)', $paid['raw_type']);
        $this->assertSame('decimal', $paid['type']);
    }

    public function testMultiEntityRouteGrouping(): void
    {
        $spec = $this->spec('order_with_detail');
        $out = (new YdSpecCompiler())->compile($spec);
        $groups = [];
        foreach ($out['entities'] as $e) {
            $groups[$e['name']] = $e['route_group'];
        }
        $this->assertSame('order', $groups['Order']);
        $this->assertSame('order-detail', $groups['OrderDetail']);
        $this->assertTrue($out['entities'][0]['is_main']);
        $this->assertFalse($out['entities'][1]['is_main']);
    }

    public function testCompileSchemaPatchContainsAllTables(): void
    {
        $spec = $this->spec('order_with_detail');
        $out = (new YdSpecCompiler())->compile($spec);
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS `orders`', $out['schema_patch']);
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS `order_details`', $out['schema_patch']);
        $this->assertSame($out['schema_patch'], $out['update_sql']);
    }

    public function testEnumStatusIsNotStatusSwitch(): void
    {
        $spec = $this->spec('article');
        $out = (new YdSpecCompiler())->compile($spec);
        $this->assertFalse($out['entities'][0]['has_status_switch']);
    }
}
