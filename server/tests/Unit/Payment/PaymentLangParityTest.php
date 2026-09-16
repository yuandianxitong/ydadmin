<?php

declare(strict_types=1);

namespace tests\Unit\Payment;

use tests\TestCase;

/** payment 语言组的中英文键必须一一对应：缺英文键时 lang() 会原样返回 key，客户端直接看到 payment.xxx。 */
final class PaymentLangParityTest extends TestCase
{
    public function test_zh_and_en_payment_groups_define_the_same_non_empty_keys(): void
    {
        /** @var array<string, string> $zh */
        $zh = require base_path('resource/lang/zh_CN/payment.php');
        /** @var array<string, string> $en */
        $en = require base_path('resource/lang/en/payment.php');

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
}
