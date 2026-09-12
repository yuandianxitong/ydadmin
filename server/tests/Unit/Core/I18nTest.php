<?php

declare(strict_types=1);

namespace tests\Unit\Core;

use app\exception\Handler;
use app\middleware\LocaleMiddleware;
use core\base\Controller;
use core\exception\AuthException;
use core\exception\ForbiddenException;
use core\exception\NotFoundException;
use core\exception\ValidationException;
use core\response\Api;
use core\validation\ValidatorFactory;
use Psr\Log\NullLogger;
use support\Context;
use tests\TestCase;
use Webman\Http\Request;
use Webman\Http\Response;

final class I18nTest extends TestCase
{
    /** Laravel 内置规则里业务常用的一批：zh_CN 必须有自己的中文消息，缺了会回落到 en。 */
    private const COMMON_RULES = [
        'required', 'string', 'integer', 'numeric', 'array', 'boolean', 'email',
        'min.numeric', 'min.string', 'min.array', 'min.file',
        'max.numeric', 'max.string', 'max.array', 'max.file',
        'between.numeric', 'between.string', 'between.array', 'between.file',
        'in', 'not_in', 'unique', 'exists', 'alpha_dash', 'date', 'date_format', 'regex',
        'confirmed', 'different', 'same',
        'size.numeric', 'size.string', 'size.array', 'size.file',
        'url', 'ip', 'json', 'digits', 'digits_between',
        'gt.numeric', 'gt.string', 'gt.array', 'gt.file',
        'gte.numeric', 'gte.string', 'gte.array', 'gte.file',
        'lt.numeric', 'lt.string', 'lt.array', 'lt.file',
        'lte.numeric', 'lte.string', 'lte.array', 'lte.file',
        'required_if', 'required_with', 'prohibited', 'present',
    ];

    private function through(string $headers): void
    {
        $request = new Request("GET /adminapi/health HTTP/1.1\r\nHost: localhost\r\n{$headers}\r\n");
        (new LocaleMiddleware())->process($request, fn (Request $r) => Api::success());
    }

    /** @return array<string, string> 各类默认文案，按当前 locale 解析 */
    private function defaultMessages(): array
    {
        $message = static fn (Response $response): string => (string) json_decode((string) $response->rawBody(), true)['message'];
        $controller = new class () extends Controller {
            public function ok(): \support\Response
            {
                return $this->success();
            }
        };
        $request = new Request("GET /adminapi/health HTTP/1.1\r\nHost: localhost\r\n\r\n");

        return [
            'api_success'   => $message(Api::success()),
            'api_paginate'  => $message(Api::paginate(['list' => [], 'pagination' => ['current_page' => 1, 'per_page' => 15, 'total' => 0, 'last_page' => 1]])),
            'controller'    => $message($controller->ok()),
            'encode_failed' => $message(Api::success(["\xB1\x31"])),
            'server_error'  => $message((new Handler(new NullLogger(), false))->render($request, new \RuntimeException('boom'))),
            'auth'          => (new AuthException())->getMessage(),
            'forbidden'     => (new ForbiddenException())->getMessage(),
            'validation'    => (new ValidationException([]))->getMessage(),
            'not_found'     => (new NotFoundException())->getMessage(),
        ];
    }

    /** @param array<string, mixed> $data @param array<string, string> $rules */
    private function firstError(array $data, array $rules): string
    {
        try {
            ValidatorFactory::validate($data, $rules);
        } catch (ValidationException $e) {
            return $e->getMessage();
        }

        return '';
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

    public function test_default_messages_are_chinese_by_default(): void
    {
        $this->assertSame([
            'api_success'   => '操作成功',
            'api_paginate'  => '操作成功',
            'controller'    => '操作成功',
            'encode_failed' => '响应序列化失败',
            'server_error'  => '服务器内部错误',
            'auth'          => '未登录或登录已过期',
            'forbidden'     => '无权限访问',
            'validation'    => '参数错误',
            'not_found'     => '数据不存在',
        ], $this->defaultMessages());
    }

    public function test_default_messages_follow_the_english_locale(): void
    {
        $this->through("think-lang: en\r\n");

        $this->assertSame([
            'api_success'   => 'Success',
            'api_paginate'  => 'Success',
            'controller'    => 'Success',
            'encode_failed' => 'Failed to encode the response',
            'server_error'  => 'Internal server error',
            'auth'          => 'Not logged in or the login has expired',
            'forbidden'     => 'Access denied',
            'validation'    => 'Invalid parameters',
            'not_found'     => 'Data not found',
        ], $this->defaultMessages());
    }

    public function test_common_validation_rules_have_their_own_chinese_messages(): void
    {
        $translator = ValidatorFactory::translator();
        $problems = [];
        foreach (self::COMMON_RULES as $rule) {
            $key = "validation.{$rule}";
            if (!$translator->hasForLocale($key, 'zh_CN')) {
                $problems[] = "zh_CN 缺少 {$key}";
            } elseif (!$translator->hasForLocale($key, 'en')) {
                $problems[] = "en 缺少 {$key}";
            } elseif ($translator->get($key, [], 'zh_CN') === $translator->get($key, [], 'en')) {
                $problems[] = "zh_CN 与 en 相同：{$key}";
            }
        }

        $this->assertSame([], $problems, implode("\n", $problems));
    }

    public function test_builtin_rule_messages_follow_the_locale(): void
    {
        $this->assertSame('code 的值无效', $this->firstError(['code' => 'a'], ['code' => 'not_in:a,b']));
        $this->assertSame('ids 必须是 2 项', $this->firstError(['ids' => [1]], ['ids' => 'array|size:2']));

        $this->through("think-lang: en\r\n");
        $this->assertSame('The selected code is invalid.', $this->firstError(['code' => 'a'], ['code' => 'not_in:a,b']));
    }
}
