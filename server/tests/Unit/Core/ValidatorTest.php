<?php

declare(strict_types=1);

namespace tests\Unit\Core;

use core\exception\ValidationException;
use core\validation\ValidatorFactory;
use support\Context;
use tests\TestCase;

final class ValidatorTest extends TestCase
{
    public function test_valid_data_returns_only_validated_fields(): void
    {
        $data = ValidatorFactory::validate(
            ['name' => 'ok', 'is_admin' => 1],
            ['name' => 'required|string|max:50']
        );
        $this->assertSame(['name' => 'ok'], $data, '未声明规则的字段不得进入返回值（字段白名单）');
    }

    public function test_failure_throws_422_with_first_error_per_field(): void
    {
        try {
            ValidatorFactory::validate(
                ['age' => 'x'],
                ['name' => 'required', 'age' => 'integer'],
                [],
                ['name' => '名称', 'age' => '年龄']
            );
            $this->fail('应抛出 ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame(422, $e->getCode());
            $this->assertSame(['name' => '名称 不能为空', 'age' => '年龄 必须是整数'], $e->errors());
            $this->assertSame('名称 不能为空', $e->getMessage());
        }
    }

    public function test_english_locale_uses_english_rule_messages(): void
    {
        Context::set('locale', 'en');
        try {
            ValidatorFactory::validate([], ['name' => 'required']);
            $this->fail('应抛出 ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('The name field is required.', $e->errors()['name']);
        }
    }

    public function test_custom_message_that_is_a_lang_key_is_translated(): void
    {
        try {
            ValidatorFactory::validate([], ['token' => 'required'], ['token.required' => 'auth.please_login']);
            $this->fail('应抛出 ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('请先登录', $e->errors()['token']);
        }
    }

    public function test_plain_custom_message_passes_through(): void
    {
        try {
            ValidatorFactory::validate([], ['name' => 'required'], ['name.required' => '请填写名称']);
            $this->fail('应抛出 ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('请填写名称', $e->errors()['name']);
        }
    }

    public function test_unique_and_exists_rules_use_the_database(): void
    {
        // 依赖 init.sql 种子：departments 有 id=1，system_configs 有 site_name
        $this->assertSame(['dept' => 1], ValidatorFactory::validate(['dept' => 1], ['dept' => 'exists:departments,id']));

        try {
            ValidatorFactory::validate(['key' => 'site_name'], ['key' => 'unique:system_configs,config_key']);
            $this->fail('应抛出 ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('key', $e->errors());
        }
    }
}
