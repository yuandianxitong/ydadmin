<?php

declare(strict_types=1);

namespace tests\Feature\Plugin;

use app\repository\plugin\PluginRepository;
use app\service\plugin\adapter\PluginMenuAdapter;
use app\service\plugin\adapter\PluginRouteGeneration;
use core\plugin\PluginStatus;
use core\saga\SagaContext;
use support\Db;
use tests\TestCase;

final class PluginHostAdaptersTest extends TestCase
{
    protected function tearDown(): void
    {
        Db::table('plugins')->whereIn('code', ['probe', 'needs-probe', 'needs-probe-off'])->delete();
        Db::table('system_configs')->where('config_key', PluginRouteGeneration::KEY)->delete();
        parent::tearDown();
    }

    public function test_reverse_dependents_are_enabled_codes_only(): void
    {
        $repo = new PluginRepository();
        $repo->create($this->row('probe', PluginStatus::ENABLED, []));
        $repo->create($this->row('needs-probe', PluginStatus::ENABLED, ['probe']));
        $disabled = $this->row('needs-probe-off', PluginStatus::DISABLED, ['probe']);
        $disabled['code'] = 'needs-probe-off';
        $repo->create($disabled);

        $this->assertSame(['needs-probe'], $repo->findReverseDependents('probe'));
    }

    public function test_route_buckets_and_manifest_adapter_add_nothing(): void
    {
        $buckets = new \app\service\plugin\adapter\PluginRouteBuckets();
        $this->assertSame(['adminapi', 'api'], $buckets->prefixes());
        $this->assertSame([], (new \app\service\plugin\adapter\PluginManifestAdapter())->validate(['code' => 'probe']));
        $this->assertSame([], (new \app\service\plugin\adapter\PluginStateAdapter())->enabledColumns('probe', []));
    }

    public function test_menu_adapter_writes_nothing(): void
    {
        $before = Db::table('menus')->count();
        $menus = new PluginMenuAdapter();
        $ctx = new SagaContext(1, []);
        $menus->install(1, 'probe', $ctx);
        $menus->compensateInstall(1, 'probe', $ctx);
        $menus->sync('probe');

        $this->assertSame($before, Db::table('menus')->count());
    }

    public function test_route_generation_bumps_only_its_own_row(): void
    {
        $kept = Db::table('system_configs')->where('config_key', 'site_name')->value('config_value');
        $generation = new PluginRouteGeneration();

        Db::connection()->transaction(function () use ($generation): void {
            $this->assertTrue($generation->shouldBump(PluginStatus::INSTALLING, PluginStatus::ENABLED, true));
            $generation->bump();
        });

        $row = Db::table('system_configs')->where('config_key', PluginRouteGeneration::KEY)->first();
        $this->assertNotNull($row);
        $this->assertSame('2', (string) $row->config_value);
        $this->assertSame($kept, Db::table('system_configs')->where('config_key', 'site_name')->value('config_value'));
        $this->assertNotContains('tenant_id', Db::schema()->getColumnListing('system_configs'));
    }

    /** @param list<string> $depends */
    private function row(string $code, int $status, array $depends): array
    {
        return [
            'code' => $code,
            'name' => $code,
            'version' => '1.0.0',
            'kind' => 'plugin',
            'depends' => $depends,
            'manifest' => ['code' => $code],
            'status' => $status,
        ];
    }
}
