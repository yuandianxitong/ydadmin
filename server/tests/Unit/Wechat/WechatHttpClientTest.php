<?php

declare(strict_types=1);

namespace tests\Unit\Wechat;

use core\wechat\exception\WechatApiException;
use core\wechat\exception\WechatException;
use core\wechat\exception\WechatUnavailableException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use tests\Support\ConfigOverride;
use tests\Support\Wechat\FakeWechatHttp;
use tests\TestCase;

/**
 * spec §3.1：基址、超时、http_errors=false、三类分类；异常消息不含 URL / 查询串 / 请求体里的任何值。
 */
final class WechatHttpClientTest extends TestCase
{
    use ConfigOverride;
    use FakeWechatHttp;

    private const SECRET = 'SECRET-abc-123';

    private const CODE = 'JSCODE-xyz-789';

    protected function tearDown(): void
    {
        $this->restoreConfig();
        parent::tearDown();
    }

    public function test_get_hits_the_api_host_with_query_and_returns_the_decoded_body(): void
    {
        $client = $this->wechatClient([self::wechatJson(['openid' => 'o-1', 'session_key' => 'sk'])]);

        $data = $client->get('sns/jscode2session', ['appid' => 'wx1', 'secret' => self::SECRET, 'js_code' => self::CODE]);

        $this->assertSame(['openid' => 'o-1', 'session_key' => 'sk'], $data);
        $requests = $this->wechatRequests();
        $this->assertCount(1, $requests);
        $request = $requests[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('https', $request->getUri()->getScheme());
        $this->assertSame('api.weixin.qq.com', $request->getUri()->getHost());
        $this->assertSame('/sns/jscode2session', $request->getUri()->getPath());
        parse_str($request->getUri()->getQuery(), $query);
        $this->assertSame(['appid' => 'wx1', 'secret' => self::SECRET, 'js_code' => self::CODE], $query);
    }

    public function test_post_json_sends_a_json_body_with_query(): void
    {
        $client = $this->wechatClient([self::wechatJson(['errcode' => 0, 'errmsg' => 'ok', 'phone_info' => ['purePhoneNumber' => '13800138000']])]);

        $data = $client->postJson('wxa/business/getuserphonenumber', ['access_token' => 'AT-1'], ['code' => '中文-code']);

        $this->assertSame('13800138000', $data['phone_info']['purePhoneNumber']);
        $request = $this->wechatRequests()[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/wxa/business/getuserphonenumber', $request->getUri()->getPath());
        $this->assertSame('access_token=AT-1', $request->getUri()->getQuery());
        $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
        $this->assertSame('{"code":"中文-code"}', (string) $request->getBody());
    }

    public function test_default_timeouts_are_applied_to_every_request(): void
    {
        $client = $this->wechatClient([self::wechatJson(['ok' => 1])]);

        $client->get('cgi-bin/token', []);

        $options = $this->wechatRequests()[0]['options'];
        $this->assertSame(5.0, $options['connect_timeout']);
        $this->assertSame(10.0, $options['timeout']);
        $this->assertFalse($options['http_errors']);
    }

    public function test_timeouts_are_read_per_request_and_non_positive_values_fall_back(): void
    {
        $client = $this->wechatClient([self::wechatJson(['ok' => 1]), self::wechatJson(['ok' => 1])]);

        $this->overrideConfig('wechat.connect_timeout', 2.5);
        $this->overrideConfig('wechat.timeout', 4);
        $client->get('cgi-bin/token', []);
        $this->overrideConfig('wechat.connect_timeout', 0);
        $this->overrideConfig('wechat.timeout', -1);
        $client->get('cgi-bin/token', []);

        $requests = $this->wechatRequests();
        $this->assertSame(2.5, $requests[0]['options']['connect_timeout']);
        $this->assertSame(4.0, $requests[0]['options']['timeout']);
        $this->assertSame(5.0, $requests[1]['options']['connect_timeout'], '0 在 Guzzle 里是无限等待，必须回退默认');
        $this->assertSame(10.0, $requests[1]['options']['timeout']);
    }

    public function test_errcode_zero_is_success(): void
    {
        $data = $this->wechatClient([self::wechatJson(['errcode' => 0, 'errmsg' => 'ok'])])->get('cgi-bin/token', []);

        $this->assertSame(['errcode' => 0, 'errmsg' => 'ok'], $data);
    }

    /** @return iterable<string, array{int|string, int}> */
    public static function errcodes(): iterable
    {
        yield 'int' => [40029, 40029];
        yield 'numeric string' => ['40163', 40163];
        yield 'negative system busy' => [-1, -1];
    }

    #[DataProvider('errcodes')]
    public function test_non_zero_errcode_is_an_api_exception(int|string $raw, int $expected): void
    {
        $client = $this->wechatClient([self::wechatJson(['errcode' => $raw, 'errmsg' => 'invalid code, rid: 66a1'])]);

        try {
            $client->get('sns/jscode2session', ['js_code' => self::CODE, 'secret' => self::SECRET]);
            $this->fail('errcode≠0 必须抛 WechatApiException');
        } catch (WechatApiException $e) {
            $this->assertSame($expected, $e->getErrcode());
            $this->assertSame('invalid code, rid: 66a1', $e->getErrmsg());
            $this->assertSame('sns/jscode2session', $e->getApi());
            $this->assertSame("微信接口 sns/jscode2session 返回 errcode {$expected}", $e->getMessage());
        }
    }

    public function test_non_numeric_errcode_is_treated_as_failure(): void
    {
        $this->expectException(WechatApiException::class);

        $this->wechatClient([self::wechatJson(['errcode' => 'oops'])])->get('cgi-bin/token', []);
    }

    /** @return iterable<string, array{\Closure(): (Response|\Throwable)}> */
    public static function unavailableAnswers(): iterable
    {
        yield 'http 500' => [static fn (): Response => new Response(500, [], 'Internal Server Error')];
        yield 'http 404' => [static fn (): Response => new Response(404, [], '{"errcode":0}')];
        yield 'not json' => [static fn (): Response => new Response(200, [], '<html>busy</html>')];
        yield 'json scalar' => [static fn (): Response => new Response(200, [], '"just a string"')];
        yield 'empty body' => [static fn (): Response => new Response(200, [], '')];
        yield 'connect failure with url in message' => [static fn (): \Throwable => new ConnectException(
            'cURL error 7: Failed to connect https://api.weixin.qq.com/sns/jscode2session?secret=' . self::SECRET . '&js_code=' . self::CODE,
            new Request('GET', 'https://api.weixin.qq.com/sns/jscode2session?secret=' . self::SECRET),
        )];
    }

    /** @param \Closure(): (Response|\Throwable) $make */
    #[DataProvider('unavailableAnswers')]
    public function test_transport_and_malformed_answers_are_unavailable_without_leaking_query_values(\Closure $make): void
    {
        $client = $this->wechatClient([$make()]);

        try {
            $client->get('sns/jscode2session', ['appid' => 'wx1', 'secret' => self::SECRET, 'js_code' => self::CODE]);
            $this->fail('必须抛 WechatUnavailableException');
        } catch (WechatUnavailableException $e) {
            $this->assertStringContainsString('sns/jscode2session', $e->getMessage());
            $this->assertStringNotContainsString(self::SECRET, $e->getMessage());
            $this->assertStringNotContainsString(self::CODE, $e->getMessage());
            $this->assertStringNotContainsString('api.weixin.qq.com', $e->getMessage(), '消息不得含 URL');
            $this->assertNull($e->getPrevious(), 'Guzzle 异常消息带完整 URL（含 secret），不能挂成 previous');
        }
    }

    public function test_all_wechat_exceptions_share_the_base_class_and_are_not_business_exceptions(): void
    {
        foreach ([WechatApiException::class, WechatUnavailableException::class, \core\wechat\exception\WechatNotConfiguredException::class] as $class) {
            $this->assertTrue(is_subclass_of($class, WechatException::class), "{$class} 必须继承 WechatException");
            $this->assertFalse(is_subclass_of($class, \core\exception\BusinessException::class), "{$class} 不得继承 BusinessException");
            $this->assertTrue((new \ReflectionClass($class))->isFinal(), "{$class} 应为 final");
        }
        $this->assertTrue(is_subclass_of(WechatException::class, \RuntimeException::class));
    }

    /** @return iterable<string, array{string}> */
    public static function illegalApis(): iterable
    {
        yield 'parent traversal' => ['../cgi-bin/token'];
        yield 'protocol relative host' => ['//evil.example.com/x'];
        yield 'absolute url' => ['https://evil.example.com/x'];
        yield 'query in path' => ['cgi-bin/token?secret=x'];
        yield 'uppercase' => ['CGI-BIN/token'];
        yield 'empty' => [''];
    }

    #[DataProvider('illegalApis')]
    public function test_illegal_api_paths_are_rejected_before_any_request(string $api): void
    {
        $client = $this->wechatClient([]);

        try {
            $client->get($api, []);
            $this->fail('非法接口路径必须抛 InvalidArgumentException');
        } catch (\InvalidArgumentException) {
            $this->assertSame([], $this->wechatRequests());
        }
    }
}
