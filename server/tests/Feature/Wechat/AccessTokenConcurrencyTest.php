<?php

declare(strict_types=1);

namespace tests\Feature\Wechat;

use core\wechat\AccessTokenProvider;
use core\wechat\WechatAppConfig;
use core\wechat\WechatHttpClient;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use support\Redis;
use tests\Support\ConcurrentWorkers;
use tests\TestCase;

/**
 * spec §3.2 / §9.1：两个 worker 进程同时发现缓存未命中，只能有一个去调 cgi-bin/token（微信对它有每日上限，
 * 多 worker 同时刷新还会让先拿到的 token 立即失效）。
 *
 * fork 出来的子进程没法共享同一个 MockHandler，所以每个子进程自带一个 handler：被调用时往 Redis 计数键 INCR，
 * 再故意睡 300ms 模拟网络耗时，确保另一方一定撞上锁、进入等待循环。父进程断言计数恰为 1、两边拿到同一个 token、
 * 两个子进程的执行区间确实重叠。
 *
 * 子进程继承了父进程的 phpredis socket（support\Redis 在非协程下复用同一个连接对象），先各自 connect() 重连，
 * 否则两个进程的命令会交错写进同一个 socket。实测 phpredis 6.3：子进程重连不影响父进程的原连接。
 */
final class AccessTokenConcurrencyTest extends TestCase
{
    use ConcurrentWorkers;

    /** @var list<string> */
    private array $keys = [];

    protected function tearDown(): void
    {
        try {
            if ($this->keys !== []) {
                Redis::del(...$this->keys);
            }
            $this->keys = [];
            $this->cleanupConcurrencyFiles();
        } finally {
            parent::tearDown();
        }
    }

    public function test_two_workers_missing_the_cache_fetch_the_token_exactly_once(): void
    {
        $appId = 'wxconc' . bin2hex(random_bytes(6));
        $counterKey = 'test:wechat_token_fetches:' . $appId;
        $this->keys = ['wechat:access_token:' . $appId, 'wechat:access_token_lock:' . $appId, $counterKey];
        $config = new WechatAppConfig($appId, 'SECRET-concurrency');
        /** @var array{host: string, port: int, password?: string, database: int} $redisConfig */
        $redisConfig = config('redis.default');

        $results = $this->runConcurrently(2, static function () use ($config, $counterKey, $redisConfig): array {
            $client = Redis::connection()->client();
            $client->connect((string) $redisConfig['host'], (int) $redisConfig['port']);
            if ((string) ($redisConfig['password'] ?? '') !== '') {
                $client->auth((string) $redisConfig['password']);
            }
            $client->select((int) $redisConfig['database']);

            $handler = static function (RequestInterface $request, array $options) use ($counterKey) {
                Redis::incr($counterKey);
                usleep(300_000);

                return Create::promiseFor(new Response(200, ['Content-Type' => 'application/json'], '{"access_token":"TOKEN-CONC","expires_in":7200}'));
            };
            $provider = new AccessTokenProvider(new WechatHttpClient(HandlerStack::create($handler)));

            return ['token' => $provider->token($config)];
        });

        $this->assertWorkersOverlapped($results);
        $this->assertSame(['TOKEN-CONC', 'TOKEN-CONC'], array_column($results, 'token'));
        $this->assertSame('1', (string) Redis::get($counterKey), '两个进程同时未命中，只能调一次 cgi-bin/token');
    }
}
