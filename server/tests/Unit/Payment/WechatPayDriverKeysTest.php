<?php

declare(strict_types=1);

namespace tests\Unit\Payment;

use core\payment\driver\WechatPayDriver;
use core\payment\dto\CreateOrderRequest;
use core\payment\exception\PaymentConfigException;
use core\payment\TradeType;
use tests\Support\Payment\WechatPayFixture;
use tests\TestCase;
use WeChatPay\Builder;

final class WechatPayDriverKeysTest extends TestCase
{
    private WechatPayFixture $fx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fx = new WechatPayFixture();
    }

    protected function tearDown(): void
    {
        $this->fx->cleanup();
        parent::tearDown();
    }

    public function test_sdk_loads_under_php84_without_deprecation(): void
    {
        // failOnDeprecation 是这条测试的另一半：SDK 自动加载或类解析期一旦有弃用告警就红
        $this->assertTrue(class_exists(Builder::class));
    }

    public function test_constructor_does_not_touch_network_or_cert_cache(): void
    {
        $history = [];
        new WechatPayDriver($this->fx->config(), $this->fx->handler([], $history));

        $this->assertSame([], $history);
        $this->assertDirectoryDoesNotExist($this->fx->certCacheDir());
    }

    public function test_missing_private_key_file_is_config_error(): void
    {
        $this->expectException(PaymentConfigException::class);
        $this->expectExceptionMessageMatches('/missing\.pem/');

        new WechatPayDriver($this->fx->config(overrides: ['privateKeyPath' => $this->fx->dir . '/missing.pem']));
    }

    public function test_invalid_private_key_is_config_error_without_key_material(): void
    {
        $path = $this->fx->dir . '/broken.pem';
        file_put_contents($path, "-----BEGIN PRIVATE KEY-----\nTOTALLYSECRETGARBAGE\n-----END PRIVATE KEY-----\n");

        try {
            new WechatPayDriver($this->fx->config(overrides: ['privateKeyPath' => $path]));
            $this->fail('无效私钥应抛 PaymentConfigException');
        } catch (PaymentConfigException $e) {
            $this->assertStringContainsString('broken.pem', $e->getMessage());
            $this->assertStringNotContainsString('TOTALLYSECRETGARBAGE', $e->getMessage());
        }
    }

    public function test_relative_private_key_path_resolves_from_base_path(): void
    {
        $relative = 'runtime/m5b-wechat-key-' . bin2hex(random_bytes(4)) . '.pem';
        file_put_contents(base_path($relative), $this->fx->merchantPrivatePem);

        try {
            $driver = new WechatPayDriver($this->fx->config(overrides: ['privateKeyPath' => $relative]));
            $this->assertInstanceOf(WechatPayDriver::class, $driver);
        } finally {
            unlink(base_path($relative));
        }
    }

    public function test_mch_id_with_path_characters_is_config_error(): void
    {
        // 商户号会拼进证书缓存目录，必须挡住 ../ 这类值
        $this->expectException(PaymentConfigException::class);

        new WechatPayDriver($this->fx->config(overrides: ['mchId' => '../../etc']));
    }

    public function test_invalid_public_key_in_public_key_mode_is_config_error(): void
    {
        $this->expectException(PaymentConfigException::class);

        new WechatPayDriver($this->fx->config(true, ['publicKey' => 'not-a-public-key']));
    }

    public function test_public_key_without_pem_envelope_is_accepted(): void
    {
        $bare = (string) preg_replace('/-----[^-]+-----|\s+/', '', $this->fx->platformPublicPem);
        $history = [];
        $driver = new WechatPayDriver(
            $this->fx->config(true, ['publicKey' => $bare]),
            $this->fx->handler([$this->fx->jsonResponse(200, ['code_url' => 'weixin://wxpay/bizpayurl?pr=abc'], true)], $history),
        );

        $result = $driver->create(new CreateOrderRequest(
            'R20260916120000' . random_int(10000000, 99999999),
            TradeType::NATIVE,
            '余额充值',
            100,
            new \DateTimeImmutable('+30 minutes'),
            'https://example.test/api/payment/notify/wechat',
        ));

        $this->assertSame(['code_url' => 'weixin://wxpay/bizpayurl?pr=abc'], $result->data);
    }
}
