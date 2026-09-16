<?php

declare(strict_types=1);

namespace tests\Unit\Wechat;

use core\contract\ConfigValueReader;
use core\wechat\exception\WechatNotConfiguredException;
use core\wechat\WechatAppConfig;
use core\wechat\WechatConfigResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use tests\TestCase;

/**
 * spec §3.4：三端各两个键，任一为空抛 WechatNotConfiguredException；消息只列键名，不带任何配置值。
 */
final class WechatConfigResolverTest extends TestCase
{
    /** @param array<string, mixed> $values */
    private function resolver(array $values): WechatConfigResolver
    {
        return new WechatConfigResolver(new class ($values) implements ConfigValueReader {
            /** @param array<string, mixed> $values */
            public function __construct(private readonly array $values)
            {
            }

            public function getConfigValue(string $key, mixed $default = null): mixed
            {
                return array_key_exists($key, $this->values) ? $this->values[$key] : $default;
            }
        });
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function sides(): iterable
    {
        yield 'mini' => ['mini', 'wechat_mini_app_id', 'wechat_mini_app_secret'];
        yield 'official' => ['official', 'wechat_official_app_id', 'wechat_official_app_secret'];
        yield 'open' => ['open', 'wechat_open_app_id', 'wechat_open_app_secret'];
    }

    #[DataProvider('sides')]
    public function test_each_side_reads_its_own_pair(string $side, string $idKey, string $secretKey): void
    {
        $resolver = $this->resolver([
            'wechat_mini_app_id'         => 'wx-mini',
            'wechat_mini_app_secret'     => 'secret-mini',
            'wechat_official_app_id'     => 'wx-official',
            'wechat_official_app_secret' => 'secret-official',
            'wechat_open_app_id'         => 'wx-open',
            'wechat_open_app_secret'     => 'secret-open',
        ]);

        $config = $resolver->{$side}();

        $this->assertInstanceOf(WechatAppConfig::class, $config);
        $this->assertSame('wx-' . $side, $config->appId);
        $this->assertSame('secret-' . $side, $config->secret);
    }

    public function test_values_are_trimmed(): void
    {
        $config = $this->resolver([
            'wechat_mini_app_id'     => "  wx-mini\n",
            'wechat_mini_app_secret' => ' secret-mini ',
        ])->mini();

        $this->assertSame('wx-mini', $config->appId);
        $this->assertSame('secret-mini', $config->secret);
    }

    #[DataProvider('sides')]
    public function test_missing_app_id_names_the_key_without_leaking_the_secret(string $side, string $idKey, string $secretKey): void
    {
        $resolver = $this->resolver([$idKey => '', $secretKey => 'TOP-SECRET-VALUE']);

        try {
            $resolver->{$side}();
            $this->fail('app_id 为空必须抛 WechatNotConfiguredException');
        } catch (WechatNotConfiguredException $e) {
            $this->assertStringContainsString($idKey, $e->getMessage());
            $this->assertStringNotContainsString($secretKey, $e->getMessage(), 'secret 在的时候不应列为缺失');
            $this->assertStringNotContainsString('TOP-SECRET-VALUE', $e->getMessage());
        }
    }

    #[DataProvider('sides')]
    public function test_missing_secret_names_the_key(string $side, string $idKey, string $secretKey): void
    {
        $this->expectException(WechatNotConfiguredException::class);
        $this->expectExceptionMessageMatches('/' . preg_quote($secretKey, '/') . '/');

        $this->resolver([$idKey => 'wx-present'])->{$side}();
    }

    /** @return iterable<string, array{mixed}> */
    public static function blankValues(): iterable
    {
        yield 'null' => [null];
        yield 'false' => [false];
        yield 'empty string' => [''];
        yield 'whitespace' => ["  \t "];
        yield 'array' => [['wx']];
    }

    #[DataProvider('blankValues')]
    public function test_non_string_or_blank_values_count_as_missing(mixed $blank): void
    {
        try {
            $this->resolver(['wechat_open_app_id' => $blank, 'wechat_open_app_secret' => $blank])->open();
            $this->fail('空白或非字符串配置必须视为缺失');
        } catch (WechatNotConfiguredException $e) {
            $this->assertStringContainsString('wechat_open_app_id', $e->getMessage());
            $this->assertStringContainsString('wechat_open_app_secret', $e->getMessage());
        }
    }
}
