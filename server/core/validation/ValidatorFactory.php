<?php

declare(strict_types=1);

namespace core\validation;

use core\exception\ValidationException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use support\Context;

final class ValidatorFactory
{
    private const DEFAULT_LOCALE = 'zh_CN';

    /**
     * 只读消息目录缓存（check:context 白名单）。当前 locale 存在 support\Context，
     * translator() 每次调用前 setLocale()。前提：非协程运行模式——Translator 是进程级单例，
     * locale 写在它身上；启用协程/异步驱动前必须改为按请求构造 Translator，否则并发请求之间 locale 会串。
     */
    private static ?Translator $translator = null;

    /** 只读校验工厂缓存（check:context 白名单）。 */
    private static ?Factory $factory = null;

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $rules
     * @param array<string, string> $messages
     * @param array<string, string> $attributes 字段显示名，如 ['name' => '名称']
     * @return array<string, mixed> 通过校验的字段（字段白名单：业务代码只能用这个返回值写库）
     * @throws ValidationException
     */
    public static function validate(array $data, array $rules, array $messages = [], array $attributes = []): array
    {
        $validator = self::factory()->make($data, $rules, $messages, $attributes);
        if ($validator->fails()) {
            $errors = [];
            foreach ($validator->errors()->toArray() as $field => $fieldMessages) {
                $errors[(string) $field] = self::resolveMessage((string) ($fieldMessages[0] ?? ''));
            }
            throw new ValidationException($errors);
        }

        return $validator->validated();
    }

    public static function translator(): Translator
    {
        if (self::$translator === null) {
            $loader = new FileLoader(new Filesystem(), base_path() . '/resource/lang');
            self::$translator = new Translator($loader, self::DEFAULT_LOCALE);
            self::$translator->setFallback('en');
        }
        self::$translator->setLocale(self::currentLocale());

        return self::$translator;
    }

    private static function currentLocale(): string
    {
        $locale = Context::get('locale');

        return is_string($locale) && $locale !== '' ? $locale : self::DEFAULT_LOCALE;
    }

    private static function factory(): Factory
    {
        if (self::$factory === null) {
            self::$factory = new Factory(self::translator());
        }
        // 确保本次校验使用当前请求的 locale
        self::translator();

        return self::$factory;
    }

    /** 自定义消息若本身是一个已存在的 lang key（如 'auth.please_login'）则翻译，否则原样返回。 */
    private static function resolveMessage(string $message): string
    {
        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)+$/', $message) === 1
            && self::translator()->has($message)) {
            return self::translator()->get($message);
        }

        return $message;
    }
}
