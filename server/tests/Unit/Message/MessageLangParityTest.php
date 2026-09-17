<?php

declare(strict_types=1);

namespace tests\Unit\Message;

use tests\TestCase;

/** message 语言组的中英文键必须一一对应：缺英文键时 lang() 会原样返回 key，前端直接看到 message.xxx。 */
final class MessageLangParityTest extends TestCase
{
    public function test_zh_and_en_message_groups_define_the_same_non_empty_keys(): void
    {
        $zhFile = base_path('resource/lang/zh_CN/message.php');
        $enFile = base_path('resource/lang/en/message.php');
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

    public function test_m6b_admin_keys_are_present(): void
    {
        foreach (['builtin_template_undeletable', 'template_code_exists', 'template_code_format'] as $key) {
            $this->assertNotSame("message.{$key}", lang("message.{$key}", [], 'zh_CN'), $key);
            $this->assertNotSame("message.{$key}", lang("message.{$key}", [], 'en'), $key);
        }
        $this->assertSame('内置模板不可删除', lang('message.builtin_template_undeletable', [], 'zh_CN'));
    }
}
