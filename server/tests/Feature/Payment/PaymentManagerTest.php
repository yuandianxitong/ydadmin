<?php

declare(strict_types=1);

namespace tests\Feature\Payment;

use core\payment\driver\AlipayDriver;
use core\payment\driver\WechatPayDriver;
use core\payment\exception\PaymentConfigException;
use core\payment\GatewayResolver;
use core\payment\PaymentManager;
use support\Container;
use tests\Support\ApiTestCase;
use tests\Support\ConfigOverride;
use tests\Support\Payment\PaymentKeys;

/**
 * spec §5.8 / §6：PaymentManager 每次现读 payment 组配置、现 new 驱动；gateway() 不看 enabled 开关，
 * 只要求凭据齐全；isEnabled() 只拦新下单。
 */
final class PaymentManagerTest extends ApiTestCase
{
    use ConfigOverride;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = PaymentKeys::tempDir();
        $this->overrideConfig('payment.wechat_cert_dir', $this->dir . '/certs');
    }

    protected function tearDown(): void
    {
        $this->restoreConfig();
        PaymentKeys::removeDir($this->dir);
        parent::tearDown();
    }

    private function manager(): PaymentManager
    {
        return Container::get(PaymentManager::class);
    }

    private function fillAlipay(): void
    {
        $app = PaymentKeys::rsaPair();
        $alipay = PaymentKeys::rsaPair();
        $this->setConfig('pay_alipay_app_id', '2021000000000001');
        $this->setConfig('pay_alipay_private_key', $app['private']);
        $this->setConfig('pay_alipay_public_key', $alipay['public']);
    }

    private function fillWechat(): void
    {
        $merchant = PaymentKeys::rsaPair();
        $platform = PaymentKeys::rsaPair();
        $keyPath = $this->dir . '/apiclient_key.pem';
        file_put_contents($keyPath, $merchant['private']);
        $this->setConfig('pay_wechat_app_id', 'wx0000000000000001');
        $this->setConfig('pay_wechat_mch_id', '1900000001');
        $this->setConfig('pay_wechat_api_v3_key', str_repeat('k', 32));
        $this->setConfig('pay_wechat_serial_no', '3775B6A45ACD588826D15E583A95F5DD00000001');
        $this->setConfig('pay_wechat_private_key_path', $keyPath);
        // 公钥模式：构造与测试都不需要平台证书
        $this->setConfig('pay_wechat_public_key_id', 'PUB_KEY_ID_0000000000000000000000000000000001');
        $this->setConfig('pay_wechat_public_key', $platform['public']);
    }

    public function test_resolver_binding_is_the_manager_singleton(): void
    {
        $this->assertInstanceOf(PaymentManager::class, Container::get(GatewayResolver::class));
        $this->assertSame(Container::get(PaymentManager::class), Container::get(GatewayResolver::class));
    }

    public function test_channels_are_disabled_by_seed_and_unknown_channel_is_never_enabled(): void
    {
        $this->assertFalse($this->manager()->isEnabled('wechat'));
        $this->assertFalse($this->manager()->isEnabled('alipay'));
        $this->assertFalse($this->manager()->isEnabled('paypal'));
    }

    public function test_is_enabled_follows_config_without_restart(): void
    {
        $this->setConfig('pay_alipay_enabled', '1');
        $this->assertTrue($this->manager()->isEnabled('alipay'));
        $this->assertFalse($this->manager()->isEnabled('wechat'), '开关按渠道独立');

        $this->setConfig('pay_alipay_enabled', '0');
        $this->assertFalse($this->manager()->isEnabled('alipay'), '关掉后同一个单例立刻读到新值');
    }

    public function test_gateway_rejects_unknown_channel(): void
    {
        $this->expectException(PaymentConfigException::class);
        $this->manager()->gateway('paypal');
    }

    public function test_gateway_requires_every_alipay_credential(): void
    {
        foreach (['pay_alipay_app_id', 'pay_alipay_private_key', 'pay_alipay_public_key'] as $missing) {
            $this->fillAlipay();
            $this->setConfig($missing, '   ');
            try {
                $this->manager()->gateway('alipay');
                $this->fail("缺 {$missing} 时必须抛 PaymentConfigException");
            } catch (PaymentConfigException $e) {
                $this->assertStringContainsString($missing, $e->getMessage(), '日志要能看出缺的是哪一项');
            }
        }
    }

    public function test_gateway_builds_alipay_driver_regardless_of_enabled_switch(): void
    {
        $this->fillAlipay();
        $this->assertFalse($this->manager()->isEnabled('alipay'));

        $this->assertInstanceOf(AlipayDriver::class, $this->manager()->gateway('alipay'), '关单、退款、对账、回调不看开关（spec §5.8）');
    }

    public function test_gateway_requires_every_wechat_credential(): void
    {
        $required = ['pay_wechat_app_id', 'pay_wechat_mch_id', 'pay_wechat_api_v3_key', 'pay_wechat_serial_no', 'pay_wechat_private_key_path'];
        foreach ($required as $missing) {
            $this->fillWechat();
            $this->setConfig($missing, '');
            try {
                $this->manager()->gateway('wechat');
                $this->fail("缺 {$missing} 时必须抛 PaymentConfigException");
            } catch (PaymentConfigException $e) {
                $this->assertStringContainsString($missing, $e->getMessage());
            }
        }
    }

    public function test_wechat_public_key_id_and_public_key_must_be_filled_together(): void
    {
        $this->fillWechat();
        $this->setConfig('pay_wechat_public_key', '');
        try {
            $this->manager()->gateway('wechat');
            $this->fail('只填公钥 ID 必须抛异常');
        } catch (PaymentConfigException) {
        }

        $this->fillWechat();
        $this->setConfig('pay_wechat_public_key_id', '');
        $this->expectException(PaymentConfigException::class);
        $this->manager()->gateway('wechat');
    }

    public function test_gateway_builds_wechat_driver_in_public_key_mode(): void
    {
        $this->fillWechat();

        $this->assertInstanceOf(WechatPayDriver::class, $this->manager()->gateway('wechat'));
    }

    public function test_relative_private_key_path_resolves_from_base_path(): void
    {
        $this->fillWechat();
        $relative = 'runtime/tests-payment-' . bin2hex(random_bytes(4)) . '/apiclient_key.pem';
        mkdir(dirname(base_path($relative)), 0o700, true);
        copy($this->dir . '/apiclient_key.pem', base_path($relative));
        $this->setConfig('pay_wechat_private_key_path', $relative);

        try {
            $this->assertInstanceOf(WechatPayDriver::class, $this->manager()->gateway('wechat'));
        } finally {
            @unlink(base_path($relative));
            @rmdir(dirname(base_path($relative)));
        }
    }

    public function test_each_call_builds_a_fresh_driver(): void
    {
        $this->fillAlipay();

        $this->assertNotSame($this->manager()->gateway('alipay'), $this->manager()->gateway('alipay'), '不缓存驱动：改了凭据下一次调用就要生效');
    }
}
