<?php

declare(strict_types=1);

namespace tests\Feature\System;

use tests\TestCase;

/**
 * spec §14 守卫：M2b 的 OpenAPI 推导器要从生成的控制器私有校验方法里读接口参数，
 * 所以那个方法 return 的必须是字面量数组——键是字段名、值是字符串，唯一允许的插值是 {$required}。
 *
 * 直接盯黄金期望文件：GoldenModuleTest 已经保证产物与它逐字节相等，这里就不必再跑一遍渲染。
 */
final class GeneratorRulesLiteralTest extends TestCase
{
    private const CONTROLLER_FIXTURE = '/tests/fixtures/generated/app/adminapi/controller/demo/GenArticleController.php';

    public function testRulesArrayIsLiteral(): void
    {
        $body = $this->arrayBodyOf('private function genArticleRules(string $scene): array');

        $lines = array_values(array_filter(
            explode("\n", $body),
            static fn (string $line): bool => trim($line) !== ''
        ));
        self::assertNotSame([], $lines, '规则数组是空的');
        foreach ($lines as $line) {
            self::assertMatchesRegularExpression(
                '/^ {12}\'[a-z0-9_]+\'\s+=> (\'[^\']*\'|"\{\$required\}(\|[^"]*)?"),$/',
                $line,
                "规则数组必须是字面量（键是字段名、值是字符串），这一行不是：{$line}"
            );
        }

        // 只允许 {$required} 这一个插值：别的变量意味着规则要到运行时才成形，推导器读不出来
        preg_match_all('/\{\$([a-zA-Z_][a-zA-Z0-9_]*)\}/', $body, $matches);
        self::assertSame(['required'], array_values(array_unique($matches[1])));
    }

    public function testMessagesAreLangKeys(): void
    {
        $body = $this->arrayBodyOf('private function messages(): array');

        $lines = array_values(array_filter(
            explode("\n", $body),
            static fn (string $line): bool => trim($line) !== ''
        ));
        self::assertNotSame([], $lines, '消息数组是空的');
        foreach ($lines as $line) {
            self::assertMatchesRegularExpression(
                '/^ {12}\'[a-z0-9_.*]+\'\s+=> \'[a-z_]+\.[a-z0-9_]+\',$/',
                $line,
                "messages() 的值必须是 lang key 字符串，这一行不是：{$line}"
            );
        }
    }

    /** 取出某个方法体里 return [ ... ]; 之间的原文。 */
    private function arrayBodyOf(string $signature): string
    {
        $source = (string) file_get_contents(base_path() . self::CONTROLLER_FIXTURE);
        $start = strpos($source, $signature);
        self::assertNotFalse($start, "生成的控制器里找不到：{$signature}");

        $open = strpos($source, "return [\n", (int) $start);
        self::assertNotFalse($open, "{$signature} 里没有 return 数组");
        $close = strpos($source, "\n        ];", (int) $open);
        self::assertNotFalse($close, "{$signature} 的 return 数组没有闭合");

        $from = (int) $open + strlen("return [\n");

        return substr($source, $from, (int) $close - $from);
    }
}
