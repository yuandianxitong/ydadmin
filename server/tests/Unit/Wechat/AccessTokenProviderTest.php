<?php

declare(strict_types=1);

namespace tests\Unit\Wechat;

use core\wechat\AccessTokenProvider;
use core\wechat\exception\WechatApiException;
use core\wechat\exception\WechatUnavailableException;
use core\wechat\WechatAppConfig;
use support\Redis;
use tests\Support\ConfigOverride;
use tests\Support\Wechat\FakeWechatHttp;
use tests\TestCase;

/**
 * spec §3.2：缓存命中不请求；未命中 SET NX 抢锁、拿到后调 cgi-bin/token 写缓存（TTL = max(expires_in − 300, 60)）、
 * Lua 比值删锁；抢不到锁的每 200ms 读缓存、至多 token_wait_ms，仍无则 WechatUnavailableException。
 * 两个进程真并发只取一次 token 的用例在 tests/Feature/Wechat/AccessTokenConcurrencyTest.php。
 */
final class AccessTokenProviderTest extends TestCase
{
    use ConfigOverride;
    use FakeWechatHttp;

    private const SECRET = 'SECRET-token-test';

    /** @var list<string> */
    private array $appIds = [];

    protected function tearDown(): void
    {
        try {
            foreach ($this->appIds as $appId) {
                Redis::del('wechat:access_token:' . $appId, 'wechat:access_token_lock:' . $appId);
            }
            $this->appIds = [];
            $this->restoreConfig();
        } finally {
            parent::tearDown();
        }
    }

    private function config(): WechatAppConfig
    {
        $appId = 'wxtok' . bin2hex(random_bytes(6));
        $this->appIds[] = $appId;

        return new WechatAppConfig($appId, self::SECRET);
    }

    /** @param list<\Psr\Http\Message\ResponseInterface|\Throwable> $responses */
    private function provider(array $responses): AccessTokenProvider
    {
        return new AccessTokenProvider($this->wechatClient($responses));
    }

    public function test_miss_fetches_with_client_credential_and_caches_with_a_safety_margin(): void
    {
        $config = $this->config();
        $provider = $this->provider([self::wechatJson(['access_token' => 'TOKEN-1', 'expires_in' => 7200])]);

        $this->assertSame('TOKEN-1', $provider->token($config));

        $request = $this->wechatRequests()[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/cgi-bin/token', $request->getUri()->getPath());
        parse_str($request->getUri()->getQuery(), $query);
        $this->assertSame(['grant_type' => 'client_credential', 'appid' => $config->appId, 'secret' => self::SECRET], $query);

        $this->assertSame('TOKEN-1', Redis::get('wechat:access_token:' . $config->appId));
        $ttl = (int) Redis::ttl('wechat:access_token:' . $config->appId);
        $this->assertGreaterThanOrEqual(6890, $ttl, 'TTL 应为 expires_in − 300');
        $this->assertLessThanOrEqual(6900, $ttl);
        $this->assertSame(0, (int) Redis::exists('wechat:access_token_lock:' . $config->appId), '取完必须释放锁');
    }

    public function test_cached_token_is_returned_without_calling_wechat(): void
    {
        $config = $this->config();
        Redis::set('wechat:access_token:' . $config->appId, 'CACHED', 'EX', 600);
        $provider = $this->provider([]);   // 队列为空：一旦请求就 OutOfBoundsException

        $this->assertSame('CACHED', $provider->token($config));
        $this->assertSame([], $this->wechatRequests());
    }

    public function test_short_expiry_floors_the_ttl_at_sixty_seconds(): void
    {
        $config = $this->config();
        $provider = $this->provider([self::wechatJson(['access_token' => 'SHORT', 'expires_in' => 200])]);

        $provider->token($config);

        $ttl = (int) Redis::ttl('wechat:access_token:' . $config->appId);
        $this->assertGreaterThanOrEqual(55, $ttl);
        $this->assertLessThanOrEqual(60, $ttl);
    }

    public function test_invalidate_forces_a_refetch(): void
    {
        $config = $this->config();
        $provider = $this->provider([
            self::wechatJson(['access_token' => 'TOKEN-A', 'expires_in' => 7200]),
            self::wechatJson(['access_token' => 'TOKEN-B', 'expires_in' => 7200]),
        ]);

        $this->assertSame('TOKEN-A', $provider->token($config));
        $this->assertSame('TOKEN-A', $provider->token($config), '第二次命中缓存');
        $provider->invalidate($config->appId);
        $this->assertSame('TOKEN-B', $provider->token($config));

        $this->assertCount(2, $this->wechatRequests());
    }

    public function test_lock_held_elsewhere_and_no_token_appearing_times_out_without_calling_wechat(): void
    {
        $config = $this->config();
        Redis::set('wechat:access_token_lock:' . $config->appId, 'someone-else', 'EX', 10);
        $this->overrideConfig('wechat.token_wait_ms', 300);
        $provider = $this->provider([]);

        $startedAt = hrtime(true);
        try {
            $provider->token($config);
            $this->fail('锁被别人持有且始终没有 token 时必须 WechatUnavailableException');
        } catch (WechatUnavailableException $e) {
            $elapsedMs = (hrtime(true) - $startedAt) / 1_000_000;
            $this->assertGreaterThanOrEqual(250, $elapsedMs, '应等待约 token_wait_ms');
            $this->assertLessThan(1500, $elapsedMs, '不应无限等待');
            $this->assertStringNotContainsString(self::SECRET, $e->getMessage());
        }
        $this->assertSame([], $this->wechatRequests());
        $this->assertSame('someone-else', Redis::get('wechat:access_token_lock:' . $config->appId), '不得删掉别人的锁');
    }

    public function test_token_appearing_while_waiting_for_the_lock_is_used(): void
    {
        $config = $this->config();
        Redis::set('wechat:access_token_lock:' . $config->appId, 'someone-else', 'EX', 10);
        // 锁被别人持有，但对方在「我读缓存未命中」之前已写好缓存：第一次读就命中，不进等待循环
        Redis::set('wechat:access_token:' . $config->appId, 'FROM-OTHER', 'EX', 600);

        $this->assertSame('FROM-OTHER', $this->provider([])->token($config));
        $this->assertSame([], $this->wechatRequests());
    }

    public function test_fetch_failure_releases_the_lock_and_propagates(): void
    {
        $config = $this->config();
        $provider = $this->provider([self::wechatJson(['errcode' => 40125, 'errmsg' => 'invalid appsecret'])]);

        try {
            $provider->token($config);
            $this->fail('errcode≠0 必须抛出');
        } catch (WechatApiException $e) {
            $this->assertSame(40125, $e->getErrcode());
            $this->assertStringNotContainsString(self::SECRET, $e->getMessage());
        }
        $this->assertSame(0, (int) Redis::exists('wechat:access_token_lock:' . $config->appId), '失败也要释放锁');
        $this->assertSame(0, (int) Redis::exists('wechat:access_token:' . $config->appId));
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function malformedTokenAnswers(): iterable
    {
        yield 'missing access_token' => [['expires_in' => 7200]];
        yield 'empty access_token' => [['access_token' => '', 'expires_in' => 7200]];
        yield 'missing expires_in' => [['access_token' => 'X']];
        yield 'string expires_in' => [['access_token' => 'X', 'expires_in' => '7200']];
    }

    /** @param array<string, mixed> $body */
    #[\PHPUnit\Framework\Attributes\DataProvider('malformedTokenAnswers')]
    public function test_malformed_token_answer_is_unavailable_and_releases_the_lock(array $body): void
    {
        $config = $this->config();

        try {
            $this->provider([self::wechatJson($body)])->token($config);
            $this->fail('应答缺字段必须 WechatUnavailableException');
        } catch (WechatUnavailableException) {
            $this->assertSame(0, (int) Redis::exists('wechat:access_token_lock:' . $config->appId));
            $this->assertSame(0, (int) Redis::exists('wechat:access_token:' . $config->appId));
        }
    }
}
