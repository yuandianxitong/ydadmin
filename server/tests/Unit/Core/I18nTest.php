<?php

declare(strict_types=1);

namespace tests\Unit\Core;

use app\middleware\LocaleMiddleware;
use core\response\Api;
use support\Context;
use tests\TestCase;
use Webman\Http\Request;

final class I18nTest extends TestCase
{
    private function through(string $headers): void
    {
        $request = new Request("GET /adminapi/health HTTP/1.1\r\nHost: localhost\r\n{$headers}\r\n");
        (new LocaleMiddleware())->process($request, fn (Request $r) => Api::success());
    }

    public function test_lang_defaults_to_chinese(): void
    {
        $this->assertSame('请先登录', lang('auth.please_login'));
    }

    public function test_think_lang_en_switches_to_english(): void
    {
        $this->through("think-lang: en\r\n");
        $this->assertSame('Please login first', lang('auth.please_login'));
    }

    public function test_think_lang_wins_over_accept_language(): void
    {
        $this->through("think-lang: zh-cn\r\nAccept-Language: en\r\n");
        $this->assertSame('请先登录', lang('auth.please_login'));
    }

    public function test_accept_language_respects_q_values(): void
    {
        $this->through("Accept-Language: zh-CN;q=0.5,en;q=0.9\r\n");
        $this->assertSame('Please login first', lang('auth.please_login'));
    }

    public function test_unsupported_language_falls_back_to_chinese(): void
    {
        $this->through("Accept-Language: fr-FR\r\n");
        $this->assertSame('请先登录', lang('auth.please_login'));
    }

    public function test_resolve_maps_variants(): void
    {
        $this->assertSame('zh_CN', LocaleMiddleware::resolve('zh-cn', ''));
        $this->assertSame('en', LocaleMiddleware::resolve('en', ''));
        $this->assertSame('zh_CN', LocaleMiddleware::resolve('', 'zh_CN'));
        $this->assertSame('en', LocaleMiddleware::resolve('', 'en-US'));
        $this->assertSame('zh_CN', LocaleMiddleware::resolve('', ''));
    }

    public function test_locale_does_not_leak_across_requests(): void
    {
        $this->through("think-lang: en\r\n");
        $this->assertSame('Please login first', lang('auth.please_login'));

        // webman 在每个请求结束时销毁 Context；下一个请求未经 LocaleMiddleware 时应回到默认中文
        Context::destroy();

        $this->assertSame('请先登录', lang('auth.please_login'));
    }
}
