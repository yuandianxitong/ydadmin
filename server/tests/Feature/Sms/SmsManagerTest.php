<?php

declare(strict_types=1);

namespace tests\Feature\Sms;

use core\exception\BusinessException;
use core\sms\driver\AliyunSmsDriver;
use core\sms\SmsManager;
use support\Container;
use support\Db;
use tests\Support\ApiTestCase;

/**
 * SmsManager：每次调用现读 `sms_driver`、现 new 驱动，不缓存实例（与 StorageManager 同一条纪律）。
 * 常驻内存下只要缓存了，管理员在系统配置里换了短信服务商也不会生效——而接口一路返回成功，谁都看不出来。
 */
final class SmsManagerTest extends ApiTestCase
{
    private function manager(): SmsManager
    {
        return Container::get(SmsManager::class);
    }

    public function test_manager_is_a_container_singleton(): void
    {
        $this->assertSame($this->manager(), $this->manager());
    }

    public function test_seeded_driver_is_aliyun(): void
    {
        $this->assertSame('aliyun', $this->manager()->driverName());
    }

    public function test_driver_never_falls_back_when_credentials_are_missing(): void
    {
        // 种子里凭据都是空的：必须立刻报「配置不全」，不能静默返回一个发不出短信的驱动
        try {
            $this->manager()->driver();
            $this->fail('凭据为空时必须抛 BusinessException');
        } catch (BusinessException $e) {
            $this->assertSame(lang('business.sms_config_incomplete_aliyun'), $e->getMessage());
        }
    }

    public function test_driver_follows_the_sms_driver_config_without_restart(): void
    {
        $this->setConfig('sms_driver', 'tencent');

        try {
            $this->manager()->driver();
            $this->fail('切到 tencent 后必须走腾讯云分支');
        } catch (BusinessException $e) {
            $this->assertContains(
                $e->getMessage(),
                [lang('business.sms_config_incomplete_tencent'), lang('business.sms_tencent_sdk_missing')],
                '报的必须是腾讯云那一侧的错，不能还是阿里云的'
            );
        }
    }

    public function test_unknown_driver_name_throws_and_does_not_fall_back(): void
    {
        $this->setConfig('sms_driver', 'not-a-real-driver');

        try {
            $this->manager()->driver();
            $this->fail('未知驱动名必须抛异常');
        } catch (BusinessException $e) {
            $this->assertSame(lang('business.sms_driver_unsupported', ['driver' => 'not-a-real-driver']), $e->getMessage());
        }
    }

    public function test_driver_is_built_fresh_every_call_once_credentials_are_filled(): void
    {
        $this->setConfig('sms_access_key', 'dummy-ak');
        $this->setConfig('sms_access_secret', 'dummy-sk');
        $this->setConfig('sms_sign_name', '元点科技');

        $first = $this->manager()->driver();
        $this->assertInstanceOf(AliyunSmsDriver::class, $first);
        $this->assertNotSame($first, $this->manager()->driver(), '驱动实例不得被缓存：配置改了要立刻生效');
    }

    /** 驱动名闭环：driverName() 可能返回的值 == driverFor() 认识的值 == 种子里 sms_driver 的选项键集。 */
    public function test_driver_name_set_is_closed_over_driver_for(): void
    {
        $options = json_decode(
            (string) Db::table('system_configs')->where('config_key', 'sms_driver')->value('config_options'),
            true
        );
        $this->assertEqualsCanonicalizing(['aliyun', 'tencent'], array_keys((array) $options));

        foreach (array_keys((array) $options) as $name) {
            try {
                $this->manager()->driverFor((string) $name);
            } catch (BusinessException $e) {
                $this->assertNotSame(
                    lang('business.sms_driver_unsupported', ['driver' => $name]),
                    $e->getMessage(),
                    "driverFor('{$name}') 不认识这个驱动名，但 sms_driver 的选项里有它"
                );
            }
        }
    }

    public function test_sms_config_group_is_seeded_and_never_public(): void
    {
        $rows = Db::table('system_configs')->where('config_group', 'sms')->pluck('is_public', 'config_key')->all();
        $keys = array_map('strval', array_keys($rows));
        sort($keys);

        $this->assertSame(
            ['sms_access_key', 'sms_access_secret', 'sms_driver', 'sms_sdk_app_id', 'sms_sign_name', 'sms_template_login', 'sms_template_register'],
            $keys,
            'spec §7.1 的七个键'
        );
        foreach ($rows as $key => $isPublic) {
            $this->assertSame(0, (int) $isPublic, "{$key} 不该出现在 config/global");
        }
    }
}
