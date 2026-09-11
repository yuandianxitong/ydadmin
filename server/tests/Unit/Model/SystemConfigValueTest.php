<?php

declare(strict_types=1);

namespace tests\Unit\Model;

use app\model\system\SystemConfig;
use tests\TestCase;

/** config_value 是字符串列，按 config_type 转成业务类型（延续清单：布尔把 "false" 当真、json 把合法假值折成 []）。 */
final class SystemConfigValueTest extends TestCase
{
    public function test_boolean_is_true_only_for_truthy_words(): void
    {
        foreach (['1', 'true', 'TRUE', ' yes ', 'on', 'On'] as $value) {
            $this->assertTrue(SystemConfig::convertValueByType($value, 'boolean'), var_export($value, true));
        }
        foreach (['0', '', 'false', 'FALSE', 'no', 'off', 'null', '2'] as $value) {
            $this->assertFalse(SystemConfig::convertValueByType($value, 'boolean'), var_export($value, true));
        }
    }

    public function test_json_keeps_valid_falsy_values(): void
    {
        $this->assertFalse(SystemConfig::convertValueByType('false', 'json'));
        $this->assertSame(0, SystemConfig::convertValueByType('0', 'json'));
        $this->assertSame('', SystemConfig::convertValueByType('""', 'json'));
        $this->assertNull(SystemConfig::convertValueByType('null', 'json'));
        $this->assertSame([], SystemConfig::convertValueByType('[]', 'json'));
        $this->assertSame(['a' => 1, 'b' => [true]], SystemConfig::convertValueByType('{"a":1,"b":[true]}', 'json'));
    }

    public function test_json_falls_back_to_empty_array_when_empty_or_invalid(): void
    {
        foreach (['', '   ', '{bad', "{'a':1}"] as $value) {
            $this->assertSame([], SystemConfig::convertValueByType($value, 'json'), var_export($value, true));
        }
    }

    public function test_number_and_other_types_are_unchanged(): void
    {
        $this->assertSame(5, SystemConfig::convertValueByType('5', 'number'));
        $this->assertSame(1.5, SystemConfig::convertValueByType('1.5', 'number'));
        $this->assertSame('abc', SystemConfig::convertValueByType('abc', 'number'));
        $this->assertSame('local', SystemConfig::convertValueByType('local', 'select'));
        $this->assertSame('false', SystemConfig::convertValueByType('false', 'string'));
    }
}
