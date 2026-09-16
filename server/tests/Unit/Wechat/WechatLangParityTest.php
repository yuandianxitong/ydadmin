<?php

declare(strict_types=1);

namespace tests\Unit\Wechat;

use tests\TestCase;

/** wechat 语言组的中英文键必须一一对应：缺英文键时 lang() 会原样返回 key，客户端直接看到 wechat.xxx。 */
final class WechatLangParityTest extends TestCase
{
    public function test_zh_and_en_wechat_groups_define_the_same_non_empty_keys(): void
    {
        $zhFile = base_path('resource/lang/zh_CN/wechat.php');
        $enFile = base_path('resource/lang/en/wechat.php');
        $this->assertFileExists($zhFile);
        $this->assertFileExists($enFile);

        /** @var array<string, string> $zh */
        $zh = require $zhFile;
        /** @var array<string, string> $en */
        $en = require $enFile;

        $zhKeys = array_keys($zh);
        $enKeys = array_keys($en);
        sort($zhKeys);
        sort($enKeys);
        $this->assertSame($zhKeys, $enKeys);

        foreach ([$zh, $en] as $group) {
            foreach ($group as $key => $text) {
                $this->assertIsString($text, $key);
                $this->assertNotSame('', trim($text), $key);
            }
        }
    }

    public function test_m6a_base_keys_are_present(): void
    {
        foreach (['not_configured', 'auth_failed', 'unavailable'] as $key) {
            $this->assertNotSame("wechat.{$key}", lang("wechat.{$key}", [], 'zh_CN'), $key);
            $this->assertNotSame("wechat.{$key}", lang("wechat.{$key}", [], 'en'), $key);
        }
        $this->assertSame('微信登录未配置', lang('wechat.not_configured', [], 'zh_CN'));
    }
}
