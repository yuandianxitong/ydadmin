<?php

declare(strict_types=1);

namespace core\message;

use core\message\exception\MessageDefiniteFailure;

/**
 * 模板渲染（M6b spec §4.5）。纯函数、无状态，容器单例安全。渲染失败一律是确定失败：重试不会改变模板与变量。
 *
 * 异常消息只含变量 key 与固定短语，不含变量值（会进 message_logs.error_msg）。
 */
final class TemplateRenderer
{
    private const PLACEHOLDER = '/\$\{([a-z][a-z0-9_]*)\}/u';

    private const KEY_PATTERN = '/^[a-z][a-z0-9_]*$/';

    private const FIELD_PATTERN = '/^[a-z][a-z_]*\d*$/';

    /** 微信模板字段按类型前缀的字符上限（公众号模板消息与小程序订阅消息的官方规则，超出会被微信拒收） */
    private const FIELD_LIMITS = [
        'thing'            => 20,
        'character_string' => 32,
        'number'           => 32,
        'letter'           => 32,
        'symbol'           => 5,
        'amount'           => 10,
        'time'             => 30,
        'date'             => 30,
        'phrase'           => 5,
        'name'             => 10,
        'phone_number'     => 17,
        'car_number'       => 8,
    ];

    private const DEFAULT_FIELD_LIMIT = 20;

    /**
     * 替换 ${key} 占位符；不符合语法的 ${...} 原样保留。
     *
     * @param array<string, mixed> $vars
     * @throws MessageDefiniteFailure
     */
    public function render(string $text, array $vars): string
    {
        $result = preg_replace_callback(
            self::PLACEHOLDER,
            /** @param array<array-key, string> $match */
            fn (array $match): string => $this->value($match[1], $vars),
            $text
        );
        if ($result === null) {
            throw new MessageDefiniteFailure('invalid template text');
        }

        return $result;
    }

    /**
     * 短信模板参数：按变量定义的顺序组装（腾讯云驱动按顺序取值）。
     *
     * @param array<int|string, mixed> $variableDefs [{key, name, example}]
     * @param array<string, mixed> $vars
     * @return array<string, string>
     * @throws MessageDefiniteFailure
     */
    public function smsParams(array $variableDefs, array $vars): array
    {
        $params = [];
        foreach ($variableDefs as $def) {
            $key = is_array($def) ? ($def['key'] ?? null) : null;
            if (!is_string($key) || preg_match(self::KEY_PATTERN, $key) !== 1) {
                throw new MessageDefiniteFailure('invalid variable definition');
            }
            $params[$key] = $this->value($key, $vars);
        }

        return $params;
    }

    /**
     * 微信 data：{field: {value}}，值经 render() 后按字段前缀截断。
     *
     * @param array<int|string, mixed> $mapping {字段名: 含 ${var} 的文本}
     * @param array<string, mixed> $vars
     * @return array<string, array{value: string}>
     * @throws MessageDefiniteFailure
     */
    public function wechatData(array $mapping, array $vars): array
    {
        if ($mapping === []) {
            throw new MessageDefiniteFailure('missing field mapping');
        }

        $data = [];
        foreach ($mapping as $field => $text) {
            if (!is_string($field) || preg_match(self::FIELD_PATTERN, $field) !== 1 || !is_string($text)) {
                throw new MessageDefiniteFailure('invalid field mapping');
            }
            $value = $this->render($text, $vars);
            $limit = self::FIELD_LIMITS[rtrim($field, '0123456789')] ?? self::DEFAULT_FIELD_LIMIT;
            if (mb_strlen($value, 'UTF-8') > $limit) {
                $value = mb_substr($value, 0, $limit, 'UTF-8');
            }
            $data[$field] = ['value' => $value];
        }

        return $data;
    }

    /** @param array<string, mixed> $vars */
    private function value(string $key, array $vars): string
    {
        $value = $vars[$key] ?? null;
        if ($value === null) {
            throw new MessageDefiniteFailure("missing variable {$key}");
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if ($value instanceof \Stringable) {
            $value = (string) $value;
        }
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            throw new MessageDefiniteFailure("invalid variable {$key}");
        }

        return $value;
    }
}
