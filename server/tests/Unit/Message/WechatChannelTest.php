<?php

declare(strict_types=1);

namespace tests\Unit\Message;

use core\contract\ConfigValueReader;
use core\message\channel\AbstractWechatChannel;
use core\message\channel\WechatMiniChannel;
use core\message\channel\WechatOfficialChannel;
use core\message\ChannelMessage;
use core\message\exception\MessageDefiniteFailure;
use core\message\exception\MessageTransientFailure;
use core\wechat\AccessTokenProvider;
use core\wechat\WechatConfigResolver;
use core\wechat\WechatHttpClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use support\Container;
use support\Redis;
use tests\Support\Wechat\FakeWechatHttp;
use tests\TestCase;

/**
 * 计划设计决定 3：公众号模板消息 / 小程序订阅消息的请求形状与 errcode 分类。
 * access_token 预先写进 Redis 缓存，让 MockHandler 队列里只有发送请求（token 失效重试的用例除外）。
 */
final class WechatChannelTest extends TestCase
{
    use FakeWechatHttp;

    private const SECRET = 'SECRET-message-channel';

    private const OPENID = 'oOPENID-receiver-0001';

    private string $appId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->appId = 'wxmsg' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        try {
            Redis::del('wechat:access_token:' . $this->appId, 'wechat:access_token_lock:' . $this->appId);
            $this->restoreWechatHttp();
        } finally {
            parent::tearDown();
        }
    }

    /** @return iterable<string, array{class-string<AbstractWechatChannel>, string}> */
    public static function channels(): iterable
    {
        yield 'official' => [WechatOfficialChannel::class, 'official'];
        yield 'mini' => [WechatMiniChannel::class, 'mini'];
    }

    /** @return iterable<string, array{class-string<AbstractWechatChannel>, string, string, string}> */
    public static function channelRequests(): iterable
    {
        yield 'official' => [WechatOfficialChannel::class, 'official', '/cgi-bin/message/template/send', 'url'];
        yield 'mini' => [WechatMiniChannel::class, 'mini', '/cgi-bin/message/subscribe/send', 'page'];
    }

    /**
     * @param class-string<AbstractWechatChannel> $class
     * @param list<\Psr\Http\Message\ResponseInterface|\Throwable> $responses
     */
    private function channel(string $class, string $side, array $responses, bool $configured = true): AbstractWechatChannel
    {
        $http = $this->wechatClient($responses);
        $values = $configured
            ? ["wechat_{$side}_app_id" => $this->appId, "wechat_{$side}_app_secret" => self::SECRET]
            : [];
        $resolver = new WechatConfigResolver(new class ($values) implements ConfigValueReader {
            /** @param array<string, mixed> $values */
            public function __construct(private readonly array $values)
            {
            }

            public function getConfigValue(string $key, mixed $default = null): mixed
            {
                return array_key_exists($key, $this->values) ? $this->values[$key] : $default;
            }
        });

        return new $class($http, new AccessTokenProvider($http), $resolver);
    }

    private function cacheToken(string $token): void
    {
        Redis::set('wechat:access_token:' . $this->appId, $token, 'EX', 600);
    }

    private function message(string $link = ''): ChannelMessage
    {
        return new ChannelMessage(self::OPENID, 'TPL-001', ['thing1' => ['value' => '余额充值']], $link);
    }

    /** @return array<string, mixed> */
    private function requestBody(int $index): array
    {
        $body = json_decode((string) $this->wechatRequests()[$index]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($body);

        return $body;
    }

    /** @param class-string<AbstractWechatChannel> $class */
    #[DataProvider('channelRequests')]
    public function test_posts_body_with_link_and_cached_token(string $class, string $side, string $path, string $linkField): void
    {
        $this->cacheToken('TOKEN-cached');
        $channel = $this->channel($class, $side, [self::wechatJson(['errcode' => 0, 'errmsg' => 'ok'])]);

        $channel->send($this->message('pages/order/detail?id=1'));

        $this->assertCount(1, $this->wechatRequests());
        $request = $this->wechatRequests()[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame($path, $request->getUri()->getPath());
        parse_str($request->getUri()->getQuery(), $query);
        $this->assertSame(['access_token' => 'TOKEN-cached'], $query);
        $body = $this->requestBody(0);
        $this->assertSame(['touser', 'template_id', $linkField, 'data'], array_keys($body));
        $this->assertSame([
            'touser'      => self::OPENID,
            'template_id' => 'TPL-001',
            $linkField    => 'pages/order/detail?id=1',
            'data'        => ['thing1' => ['value' => '余额充值']],
        ], $body);
    }

    /** @param class-string<AbstractWechatChannel> $class */
    #[DataProvider('channels')]
    public function test_empty_link_is_omitted(string $class, string $side): void
    {
        $this->cacheToken('TOKEN-cached');
        $channel = $this->channel($class, $side, [self::wechatJson(['errcode' => 0])]);

        $channel->send($this->message());

        $this->assertSame(['touser', 'template_id', 'data'], array_keys($this->requestBody(0)));
    }

    /** @param class-string<AbstractWechatChannel> $class */
    #[DataProvider('channels')]
    public function test_fetches_token_when_not_cached(string $class, string $side): void
    {
        $channel = $this->channel($class, $side, [
            self::wechatJson(['access_token' => 'TOKEN-fresh', 'expires_in' => 7200]),
            self::wechatJson(['errcode' => 0]),
        ]);

        $channel->send($this->message());

        $this->assertSame('/cgi-bin/token', $this->wechatRequests()[0]['request']->getUri()->getPath());
        parse_str($this->wechatRequests()[1]['request']->getUri()->getQuery(), $query);
        $this->assertSame('TOKEN-fresh', $query['access_token']);
    }

    /** @param class-string<AbstractWechatChannel> $class */
    #[DataProvider('channels')]
    public function test_not_configured_is_definite_without_http(string $class, string $side): void
    {
        $channel = $this->channel($class, $side, [], configured: false);

        try {
            $channel->send($this->message());
            $this->fail('应抛 MessageDefiniteFailure');
        } catch (MessageDefiniteFailure $e) {
            $this->assertSame('wechat not configured', $e->getMessage());
        }
        $this->assertSame([], $this->wechatRequests());
    }

    /** @return iterable<string, array{class-string<AbstractWechatChannel>, string, int, class-string<\Throwable>}> */
    public static function errcodeClassification(): iterable
    {
        foreach (self::channels() as $name => [$class, $side]) {
            yield "{$name} -1 busy" => [$class, $side, -1, MessageTransientFailure::class];
            yield "{$name} 45009 quota" => [$class, $side, 45009, MessageTransientFailure::class];
            yield "{$name} 40003 openid" => [$class, $side, 40003, MessageDefiniteFailure::class];
            yield "{$name} 43004 unsubscribed" => [$class, $side, 43004, MessageDefiniteFailure::class];
            yield "{$name} 43101 refused" => [$class, $side, 43101, MessageDefiniteFailure::class];
            yield "{$name} 40037 template" => [$class, $side, 40037, MessageDefiniteFailure::class];
            yield "{$name} 47003 argument" => [$class, $side, 47003, MessageDefiniteFailure::class];
            yield "{$name} unlisted" => [$class, $side, 99999, MessageDefiniteFailure::class];
        }
    }

    /**
     * @param class-string<AbstractWechatChannel> $class
     * @param class-string<\Throwable> $expected
     */
    #[DataProvider('errcodeClassification')]
    public function test_errcode_classification_without_retry(string $class, string $side, int $errcode, string $expected): void
    {
        $this->cacheToken('TOKEN-cached');
        $channel = $this->channel($class, $side, [self::wechatJson(['errcode' => $errcode, 'errmsg' => 'openid ' . self::OPENID])]);

        try {
            $channel->send($this->message());
            $this->fail('应抛 ' . $expected);
        } catch (MessageDefiniteFailure | MessageTransientFailure $e) {
            $this->assertInstanceOf($expected, $e);
            $this->assertSame("wechat errcode {$errcode}", $e->getMessage());
            $this->assertNull($e->getPrevious());
        }
        $this->assertCount(1, $this->wechatRequests(), '非 token 失效类 errcode 不在通道内重试');
    }

    /** @return iterable<string, array{class-string<AbstractWechatChannel>, string, int}> */
    public static function expiredTokenErrcodes(): iterable
    {
        foreach (self::channels() as $name => [$class, $side]) {
            foreach ([40001, 40014, 42001] as $errcode) {
                yield "{$name} {$errcode}" => [$class, $side, $errcode];
            }
        }
    }

    /** @param class-string<AbstractWechatChannel> $class */
    #[DataProvider('expiredTokenErrcodes')]
    public function test_expired_token_invalidates_and_retries_once(string $class, string $side, int $errcode): void
    {
        $this->cacheToken('TOKEN-stale');
        $channel = $this->channel($class, $side, [
            self::wechatJson(['errcode' => $errcode, 'errmsg' => 'invalid credential']),
            self::wechatJson(['access_token' => 'TOKEN-new', 'expires_in' => 7200]),
            self::wechatJson(['errcode' => 0]),
        ]);

        $channel->send($this->message());

        $this->assertCount(3, $this->wechatRequests());
        parse_str($this->wechatRequests()[0]['request']->getUri()->getQuery(), $first);
        parse_str($this->wechatRequests()[2]['request']->getUri()->getQuery(), $retry);
        $this->assertSame('TOKEN-stale', $first['access_token']);
        $this->assertSame('/cgi-bin/token', $this->wechatRequests()[1]['request']->getUri()->getPath());
        $this->assertSame('TOKEN-new', $retry['access_token']);
        $this->assertSame($this->requestBody(0), $this->requestBody(2), '重试发的是同一个请求体');
    }

    /** @param class-string<AbstractWechatChannel> $class */
    #[DataProvider('expiredTokenErrcodes')]
    public function test_expired_token_twice_is_transient(string $class, string $side, int $errcode): void
    {
        $this->cacheToken('TOKEN-stale');
        $channel = $this->channel($class, $side, [
            self::wechatJson(['errcode' => $errcode]),
            self::wechatJson(['access_token' => 'TOKEN-new', 'expires_in' => 7200]),
            self::wechatJson(['errcode' => $errcode]),
        ]);

        try {
            $channel->send($this->message());
            $this->fail('应抛 MessageTransientFailure');
        } catch (MessageTransientFailure $e) {
            $this->assertSame("wechat errcode {$errcode}", $e->getMessage());
        }
        $this->assertCount(3, $this->wechatRequests(), '只重试一次');
    }

    /** @param class-string<AbstractWechatChannel> $class */
    #[DataProvider('channels')]
    public function test_retry_returning_other_errcode_is_classified_by_that_errcode(string $class, string $side): void
    {
        $this->cacheToken('TOKEN-stale');
        $channel = $this->channel($class, $side, [
            self::wechatJson(['errcode' => 40001]),
            self::wechatJson(['access_token' => 'TOKEN-new', 'expires_in' => 7200]),
            self::wechatJson(['errcode' => 43101]),
        ]);

        $this->expectException(MessageDefiniteFailure::class);
        $this->expectExceptionMessage('wechat errcode 43101');

        $channel->send($this->message());
    }

    /** @param class-string<AbstractWechatChannel> $class */
    #[DataProvider('channels')]
    public function test_unavailable_is_transient(string $class, string $side): void
    {
        $this->cacheToken('TOKEN-cached');
        $channel = $this->channel($class, $side, [
            new ConnectException('cURL error 28 access_token=TOKEN-cached', new Request('POST', 'https://api.weixin.qq.com/')),
        ]);

        try {
            $channel->send($this->message());
            $this->fail('应抛 MessageTransientFailure');
        } catch (MessageTransientFailure $e) {
            $this->assertSame('wechat unavailable', $e->getMessage());
            $this->assertNull($e->getPrevious());
        }
    }

    /** @param class-string<AbstractWechatChannel> $class */
    #[DataProvider('channels')]
    public function test_http_500_is_transient(string $class, string $side): void
    {
        $this->cacheToken('TOKEN-cached');
        $channel = $this->channel($class, $side, [self::wechatJson(['errcode' => 0], 500)]);

        try {
            $channel->send($this->message());
            $this->fail('应抛 MessageTransientFailure');
        } catch (MessageTransientFailure $e) {
            $this->assertSame('wechat unavailable', $e->getMessage());
            $this->assertNull($e->getPrevious());
        }
    }

    /** @return iterable<string, array{class-string<AbstractWechatChannel>, string, int, class-string<\Throwable>}> */
    public static function tokenEndpointErrcodes(): iterable
    {
        foreach (self::channels() as $name => [$class, $side]) {
            yield "{$name} 40125 appsecret" => [$class, $side, 40125, MessageDefiniteFailure::class];
            yield "{$name} 40001 expired-token-code" => [$class, $side, 40001, MessageDefiniteFailure::class];
            yield "{$name} 40014 expired-token-code" => [$class, $side, 40014, MessageDefiniteFailure::class];
            yield "{$name} 42001 expired-token-code" => [$class, $side, 42001, MessageDefiniteFailure::class];
            yield "{$name} -1 busy" => [$class, $side, -1, MessageTransientFailure::class];
            yield "{$name} 45009 quota" => [$class, $side, 45009, MessageTransientFailure::class];
        }
    }

    /**
     * cgi-bin/token 自身返回的 errcode 一律不触发失效重试（哪怕是 40001/40014/42001）：
     * 那是 appid/appsecret 配置问题或微信侧频控，重试只会原样再错一次、白白多打一次每日限额的 cgi-bin/token。
     *
     * @param class-string<AbstractWechatChannel> $class
     * @param class-string<\Throwable> $expected
     */
    #[DataProvider('tokenEndpointErrcodes')]
    public function test_token_endpoint_errcode_is_classified_without_retry(string $class, string $side, int $errcode, string $expected): void
    {
        // 没有缓存 token，cgi-bin/token 直接返回错误 errcode
        $channel = $this->channel($class, $side, [self::wechatJson(['errcode' => $errcode, 'errmsg' => 'invalid credential'])]);

        try {
            $channel->send($this->message());
            $this->fail('应抛 ' . $expected);
        } catch (MessageDefiniteFailure | MessageTransientFailure $e) {
            $this->assertInstanceOf($expected, $e);
            $this->assertSame("wechat errcode {$errcode}", $e->getMessage());
            $this->assertNull($e->getPrevious());
        }
        $this->assertCount(1, $this->wechatRequests(), 'cgi-bin/token 自身的 errcode 不触发失效重试，只打一次');
    }

    /** @param class-string<AbstractWechatChannel> $class */
    #[DataProvider('channels')]
    public function test_container_instance_follows_fake_http(string $class, string $side): void
    {
        unset($side);
        Container::get($class);
        $this->fakeWechatHttp([]);

        $http = (new \ReflectionProperty(AbstractWechatChannel::class, 'http'))->getValue(Container::get($class));

        $this->assertSame(Container::get(WechatHttpClient::class), $http, 'FakeWechatHttp 必须重建通道单例');
    }
}
