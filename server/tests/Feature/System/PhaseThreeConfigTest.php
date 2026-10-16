<?php

declare(strict_types=1);

namespace tests\Feature\System;

use app\repository\system\ConfigurationStateRepository;
use app\repository\system\SystemConfigRepository;
use app\service\system\SystemConfigService;
use core\database\SqlScript;
use core\exception\BusinessException;
use core\sms\SmsInterface;
use core\sms\SmsManager;
use support\Container;
use support\Db;
use tests\Support\ApiTestCase;

final class PhaseThreeConfigTest extends ApiTestCase
{
    public function test_configuration_revision_is_atomic_and_migration_never_resets_it(): void
    {
        $service = Container::get(SystemConfigService::class);
        $state = Container::get(ConfigurationStateRepository::class);
        $before = $state->current();
        $old = (string) Db::table('system_configs')->where('config_key', 'site_name')->value('config_value');
        try {
            $service->batchUpdateConfigs([['config_key' => 'site_name', 'config_value' => 'rollback'], ['config_key' => 'missing_phase_three', 'config_value' => 'x']]);
            $this->fail('Invalid batch must fail');
        } catch (BusinessException) {
        }
        $this->assertSame($before, $state->current());
        $this->assertSame($old, Db::table('system_configs')->where('config_key', 'site_name')->value('config_value'));
        $service->clearConfigCache();
        $advanced = $state->current();
        $this->assertGreaterThan($before, $advanced);
        for ($run = 0; $run < 2; $run++) {
            foreach (SqlScript::split((string) file_get_contents(base_path('database/updates/v2.0.9/update.sql'))) as $sql) {
                Db::unprepared($sql);
            }
        }
        $this->assertSame($advanced, $state->current());
    }

    public function test_committed_configuration_is_fresh_even_if_redis_invalidation_callback_fails(): void
    {
        $this->rememberConfig('site_name');
        $repository = new class () extends SystemConfigRepository {
            public function forgetCache(): void
            {
                throw new \RuntimeException('injected Redis write failure');
            }
        };
        $reader = new SystemConfigRepository();
        $old = $reader->getConfigValue('site_name');
        $service = Container::make(SystemConfigService::class);
        (new \ReflectionProperty(SystemConfigService::class, 'systemConfigRepository'))->setValue($service, $repository);
        $id = (int) Db::table('system_configs')->where('config_key', 'site_name')->value('id');
        $service->updateConfig($id, 'phase-three-new');
        $this->assertNotSame('phase-three-new', $old);
        $this->assertSame('phase-three-new', $reader->getConfigValue('site_name'));
        $this->assertSame('phase-three-new', (new SystemConfigRepository())->getConfigValue('site_name'), 'Other worker instances must see the committed revision');
    }

    public function test_already_resolved_sms_interface_tracks_driver_changes_without_restart(): void
    {
        $sms = Container::get(SmsInterface::class);
        $this->assertInstanceOf(SmsManager::class, $sms);
        $this->setConfig('sms_driver', 'aliyun');
        try {
            $sms->send('13800138000', 'TEMPLATE', []);
            $this->fail('Seed credentials are empty');
        } catch (BusinessException $e) {
            $this->assertSame(lang('business.sms_config_incomplete_aliyun'), $e->getMessage());
        }
        $this->setConfig('sms_driver', 'tencent');
        try {
            $sms->send('13800138000', 'TEMPLATE', []);
            $this->fail('Seed credentials are empty');
        } catch (BusinessException $e) {
            $this->assertSame(lang('business.sms_config_incomplete_tencent'), $e->getMessage());
        }
        $this->assertSame($sms, Container::get(SmsInterface::class));
    }

    public function test_configuration_effect_endpoint_requires_read_permission_and_contains_no_values(): void
    {
        $nobody = $this->actingAsAdmin();
        $this->get('/adminapi/system/config/effect', [], $nobody->token)->assertCode(403);
        $admin = $this->actingAsAdmin(['system.config.list']);
        $data = $this->get('/adminapi/system/config/effect', [], $admin->token)->assertOk()->data();
        $this->assertGreaterThan(0, $data['revision']);
        $this->assertSame('next_operation', $data['system_config']['effect']);
        $this->assertSame('restart_all_processes', $data['environment']['effect']);
        $this->assertArrayNotHasKey('sms_access_secret', $data);
    }

    public function test_configuration_snapshot_is_consistent_and_documents_follow_new_revisions(): void
    {
        $this->setConfig('site_name', 'snapshot-before');
        $repository = new SystemConfigRepository();
        $snapshot = $repository->snapshot();
        $this->setConfig('site_name', 'snapshot-after');
        $this->assertSame('snapshot-before', $snapshot->getConfigValue('site_name'));
        $this->assertSame('snapshot-after', $repository->snapshot()->getConfigValue('site_name'));
        $this->setConfig('login_captcha', '1');
        $documents = Container::get(\app\service\system\ApiDocService::class);
        $before = $documents->document('admin')['paths']['/adminapi/auth/login']['post']['requestBody']['content']['application/json']['schema'];
        $this->assertContains('captcha', $before['required']);
        $this->setConfig('login_captcha', '0');
        $after = $documents->document('admin')['paths']['/adminapi/auth/login']['post']['requestBody']['content']['application/json']['schema'];
        $this->assertNotContains('captcha', $after['required']);
    }

    public function test_readiness_requires_initialized_configuration_state(): void
    {
        $monitor = Container::get(\app\repository\operations\MonitoringRepository::class);
        $this->assertTrue($monitor->databaseReady());
        $original = (array) Db::table('configuration_state')->where('id', 1)->first();
        try {
            Db::table('configuration_state')->where('id', 1)->delete();
            $this->assertFalse($monitor->databaseReady());
        } finally {
            Db::table('configuration_state')->insert($original);
        }
    }
}
