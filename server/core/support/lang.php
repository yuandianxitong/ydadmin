<?php

declare(strict_types=1);

// 应用级翻译入口 lang()，读 resource/lang/{locale}/{group}.php。
// webman 自带的全局 trans() 绑定的是另一套未配置的 symfony/translation 栈，调用会原样返回 key，
// 本项目一律使用 lang()。locale 由 LocaleMiddleware 写入 support\Context，按请求隔离。

if (!function_exists('lang')) {
    /** @param array<string, mixed> $replace */
    function lang(string $key, array $replace = [], ?string $locale = null): string
    {
        return \core\validation\ValidatorFactory::translator()->get($key, $replace, $locale);
    }
}
