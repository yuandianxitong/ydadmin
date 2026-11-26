<?php

declare(strict_types=1);

namespace tests\Feature\Database;

use support\Db;
use tests\TestCase;

final class PluginSchemaTest extends TestCase
{
    public function test_plugin_tables_exist_without_tenant_or_entitlement_columns(): void
    {
        foreach (['plugins', 'plugin_migrations', 'plugin_sagas'] as $table) {
            $this->assertTrue(Db::schema()->hasTable($table), "缺少表 {$table}");
        }
        $this->assertTrue(Db::schema()->hasTable('plugin_menus'));
        $this->assertFalse(Db::schema()->hasTable('tenant_plugins'));
        $this->assertFalse(Db::schema()->hasTable('plugin_grants'));

        $pluginColumns = Db::schema()->getColumnListing('plugins');
        foreach (['code', 'depends', 'read_safe_routes', 'package_path', 'package_hash', 'manifest', 'status', 'last_error', 'installed_at'] as $column) {
            $this->assertContains($column, $pluginColumns);
        }
        $this->assertNotContains('tenant_id', $pluginColumns);
        $this->assertNotContains('entitlement', $pluginColumns);
        $this->assertNotContains('tenant_id', Db::schema()->getColumnListing('system_configs'));
    }
}
