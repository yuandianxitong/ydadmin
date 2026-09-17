<?php

declare(strict_types=1);

namespace tests\Unit\Message;

use core\message\exception\MessageDefiniteFailure;
use core\message\exception\MessageFailure;
use core\message\exception\MessageTransientFailure;
use core\message\TemplateRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use tests\TestCase;

/**
 * M6b spec §4.5 / 计划设计决定 5：占位符替换、短信参数按变量定义顺序、微信 data 按字段前缀截断。
 * 渲染失败一律是确定失败（重试也不会变好）。
 */
final class TemplateRendererTest extends TestCase
{
    private TemplateRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new TemplateRenderer();
    }

    public function test_failure_hierarchy(): void
    {
        $this->assertInstanceOf(MessageFailure::class, new MessageDefiniteFailure('x'));
        $this->assertInstanceOf(MessageFailure::class, new MessageTransientFailure('x'));
        $this->assertInstanceOf(\RuntimeException::class, new MessageFailure('x'));
    }

    public function test_render_substitutes_every_occurrence_and_scalar_types(): void
    {
        $text = '订单${order_no}支付${amount}元，${order_no}；积分${points}，首单${first}，${Upper}与${1x}原样';

        $this->assertSame(
            '订单P001支付9.9元，P001；积分30，首单1，${Upper}与${1x}原样',
            $this->renderer->render($text, ['order_no' => 'P001', 'amount' => 9.9, 'points' => 30, 'first' => true, 'unused' => 'x'])
        );
        $this->assertSame('无占位符', $this->renderer->render('无占位符', []));
        $this->assertSame('', $this->renderer->render('', []));
    }

    public function test_render_accepts_stringable(): void
    {
        $value = new class () implements \Stringable {
            public function __toString(): string
            {
                return 'S1';
            }
        };

        $this->assertSame('编号S1', $this->renderer->render('编号${no}', ['no' => $value]));
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function missingVariables(): iterable
    {
        yield 'absent' => [['other' => '1']];
        yield 'null' => [['amount' => null]];
    }

    /** @param array<string, mixed> $vars */
    #[DataProvider('missingVariables')]
    public function test_render_missing_variable_is_definite_failure(array $vars): void
    {
        $this->expectException(MessageDefiniteFailure::class);
        $this->expectExceptionMessage('missing variable amount');

        $this->renderer->render('金额${amount}', $vars);
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidValues(): iterable
    {
        yield 'array' => [['a' => 1]];
        yield 'object' => [new \stdClass()];
        yield 'invalid utf-8' => ["\xB1\x31"];
    }

    #[DataProvider('invalidValues')]
    public function test_render_invalid_variable_is_definite_failure_without_value(mixed $value): void
    {
        try {
            $this->renderer->render('值${secret}', ['secret' => $value]);
            $this->fail('应抛 MessageDefiniteFailure');
        } catch (MessageDefiniteFailure $e) {
            $this->assertSame('invalid variable secret', $e->getMessage());
        }
    }

    public function test_render_invalid_utf8_text_is_definite_failure(): void
    {
        $this->expectException(MessageDefiniteFailure::class);
        $this->expectExceptionMessage('invalid template text');

        $this->renderer->render("\xB1\x31" . '${a}', ['a' => '1']);
    }

    public function test_sms_params_follow_definition_order_not_vars_order(): void
    {
        $defs = [
            ['key' => 'order_no', 'name' => '订单号', 'example' => 'P1'],
            ['key' => 'amount', 'name' => '金额', 'example' => '1.00'],
        ];

        $params = $this->renderer->smsParams($defs, ['amount' => 12, 'extra' => 'ignored', 'order_no' => 'P9']);

        $this->assertSame(['order_no' => 'P9', 'amount' => '12'], $params);
        $this->assertSame(['order_no', 'amount'], array_keys($params), '腾讯云驱动按此顺序取值');
        $this->assertSame([], $this->renderer->smsParams([], ['a' => '1']));
    }

    public function test_sms_params_missing_variable_is_definite_failure(): void
    {
        $this->expectException(MessageDefiniteFailure::class);
        $this->expectExceptionMessage('missing variable amount');

        $this->renderer->smsParams([['key' => 'amount']], []);
    }

    /** @return iterable<string, array{array<int, mixed>}> */
    public static function invalidDefinitions(): iterable
    {
        yield 'not array' => [['amount']];
        yield 'no key' => [[['name' => '金额']]];
        yield 'bad key' => [[['key' => 'Amount']]];
        yield 'non string key' => [[['key' => 1]]];
    }

    /** @param array<int, mixed> $defs */
    #[DataProvider('invalidDefinitions')]
    public function test_sms_params_invalid_definition(array $defs): void
    {
        $this->expectException(MessageDefiniteFailure::class);
        $this->expectExceptionMessage('invalid variable definition');

        $this->renderer->smsParams($defs, ['amount' => '1']);
    }

    /** @return iterable<string, array{string, int}> */
    public static function fieldLimits(): iterable
    {
        yield 'thing' => ['thing1', 20];
        yield 'character_string' => ['character_string2', 32];
        yield 'number' => ['number3', 32];
        yield 'letter' => ['letter4', 32];
        yield 'symbol' => ['symbol5', 5];
        yield 'amount' => ['amount6', 10];
        yield 'time' => ['time7', 30];
        yield 'date' => ['date8', 30];
        yield 'phrase' => ['phrase9', 5];
        yield 'name' => ['name10', 10];
        yield 'phone_number' => ['phone_number11', 17];
        yield 'car_number' => ['car_number12', 8];
        yield 'no digits' => ['thing', 20];
        yield 'unknown prefix' => ['keyword1', 20];
    }

    #[DataProvider('fieldLimits')]
    public function test_wechat_data_truncates_by_field_prefix_in_characters(string $field, int $limit): void
    {
        $long = str_repeat('测', 40);

        $data = $this->renderer->wechatData([$field => '${v}'], ['v' => $long]);

        $this->assertSame([$field => ['value' => str_repeat('测', $limit)]], $data);

        $exact = str_repeat('a', $limit);
        $this->assertSame([$field => ['value' => $exact]], $this->renderer->wechatData([$field => $exact], []), '恰好到上限不截断');
    }

    public function test_wechat_data_renders_every_field(): void
    {
        $data = $this->renderer->wechatData(
            ['character_string1' => '${order_no}', 'amount2' => '${amount}元', 'thing3' => '余额充值'],
            ['order_no' => 'P20260917', 'amount' => '9.90']
        );

        $this->assertSame([
            'character_string1' => ['value' => 'P20260917'],
            'amount2'           => ['value' => '9.90元'],
            'thing3'            => ['value' => '余额充值'],
        ], $data);
    }

    public function test_wechat_data_empty_mapping_is_definite_failure(): void
    {
        $this->expectException(MessageDefiniteFailure::class);
        $this->expectExceptionMessage('missing field mapping');

        $this->renderer->wechatData([], ['a' => '1']);
    }

    /** @return iterable<string, array{array<int|string, mixed>}> */
    public static function invalidMappings(): iterable
    {
        yield 'non string value' => [['thing1' => ['x']]];
        yield 'numeric field' => [[0 => '${a}']];
        yield 'bad field name' => [['Thing-1' => '${a}']];
    }

    /** @param array<int|string, mixed> $mapping */
    #[DataProvider('invalidMappings')]
    public function test_wechat_data_invalid_mapping(array $mapping): void
    {
        $this->expectException(MessageDefiniteFailure::class);
        $this->expectExceptionMessage('invalid field mapping');

        $this->renderer->wechatData($mapping, ['a' => '1']);
    }

    public function test_wechat_data_missing_variable_bubbles_up(): void
    {
        $this->expectException(MessageDefiniteFailure::class);
        $this->expectExceptionMessage('missing variable order_no');

        $this->renderer->wechatData(['character_string1' => '${order_no}'], []);
    }
}
