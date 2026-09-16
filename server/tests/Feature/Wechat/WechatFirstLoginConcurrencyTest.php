<?php

declare(strict_types=1);

namespace tests\Feature\Wechat;

use app\service\wechat\WechatAuthService;
use core\exception\BusinessException;
use support\Container;
use support\Db;
use support\Redis;
use tests\Support\ApiTestCase;
use tests\Support\ConcurrentWorkers;
use tests\Support\Wechat\FakeWechatHttp;
use tests\Support\Wechat\WechatUserFixtures;

/**
 * M6a spec §4.1：同一 openid 首次登录连点两次，只能注册出一个账号。
 *
 * users 表的 openid 列没有唯一索引，唯一性完全押在 Redis 登录锁上——这里用两个 fork 出来的子进程真并发跑
 * miniLogin 来证明。子进程各自继承父进程里已经换好的假 HTTP 客户端（MockHandler 队列按进程各有一份拷贝，
 * 每个子进程都消费到同一个 openid 的 code2session 应答）。
 *
 * 子进程开跑前各自 connect() 重连 Redis（同 AccessTokenConcurrencyTest）：非协程下连接池 Pool::get() 永远返回同一个
 * nonCoroutineConnection，只把 Context 里的连接置空并不会让子进程新建连接——父子会共用一个 phpredis socket，
 * 命令和回包交错，父进程之后读到残留字节报 protocol error。登录锁与配置缓存都走这个连接，所以必须在调被测方法前重连。
 */
final class WechatFirstLoginConcurrencyTest extends ApiTestCase
{
    use ConcurrentWorkers;
    use FakeWechatHttp;
    use WechatUserFixtures;

    protected function tearDown(): void
    {
        try {
            $this->cleanupConcurrencyFiles();
            $this->restoreWechatHttp();
        } finally {
            $this->cleanupWechatFixtures();
            parent::tearDown();
        }
    }

    public function test_concurrent_first_mini_login_registers_exactly_one_user(): void
    {
        $this->configureWechatApps();
        $openid = $this->fixtureOpenid();
        $this->fakeWechatHttp([self::wechatJson(['openid' => $openid, 'session_key' => 'SK'])]);
        Container::get(WechatAuthService::class);  // 在父进程里先解析好单例，子进程直接继承

        /** @var array{host: string, port: int, password?: string, database: int} $redisConfig */
        $redisConfig = config('redis.default');

        $results = $this->runConcurrently(2, static function (int $index) use ($redisConfig): array {
            $client = Redis::connection()->client();
            $client->connect((string) $redisConfig['host'], (int) $redisConfig['port']);
            if ((string) ($redisConfig['password'] ?? '') !== '') {
                $client->auth((string) $redisConfig['password']);
            }
            $client->select((int) $redisConfig['database']);

            try {
                $login = Container::get(WechatAuthService::class)->miniLogin("code-race-{$index}", '203.0.113.9');

                return ['user_id' => $login['user_info']['id']];
            } catch (BusinessException $e) {
                return ['error_code' => $e->getCode()];
            }
        });

        $this->assertWorkersOverlapped($results);
        $this->assertSame(1, Db::table('users')->where('mini_openid', $openid)->whereNull('deleted_at')->count(), '只能注册出一个账号');

        $userIds = array_values(array_filter(array_column($results, 'user_id')));
        $this->assertNotEmpty($userIds, '至少一个进程登录成功');
        $this->assertCount(1, array_unique($userIds), '两个进程都成功时必须是同一个账号');
        foreach ($results as $result) {
            if (isset($result['error_code'])) {
                $this->assertSame(429, $result['error_code'], '失败只允许是锁冲突');
            }
        }
    }
}
