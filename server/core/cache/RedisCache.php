<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace core\cache;

use think\facade\Cache;

class RedisCache
{
    protected $redis;

    public function __construct()
    {
        $this->redis = Cache::store('redis')->handler();
    }

    /**
     * 分布式锁
     */
    public function lock(string $key, int $expire = 10): bool
    {
        $lockKey = "lock:{$key}";
        $identifier = uniqid();

        $result = $this->redis->set($lockKey, $identifier, ['NX', 'EX' => $expire]);
        return $result === 'OK';
    }

    /**
     * 释放锁
     */
    public function unlock(string $key): bool
    {
        $lockKey = "lock:{$key}";
        return $this->redis->del($lockKey) > 0;
    }

    /**
     * 计数器
     */
    public function counter(string $key, int $expire = 3600): int
    {
        $count = $this->redis->incr($key);
        if ($count === 1) {
            $this->redis->expire($key, $expire);
        }
        return $count;
    }

    /**
     * 限流器
     */
    public function rateLimit(string $key, int $maxAttempts, int $window): bool
    {
        $current = $this->redis->incr($key);
        if ($current === 1) {
            $this->redis->expire($key, $window);
        }

        return $current <= $maxAttempts;
    }

    /**
     * 队列推送
     */
    public function push(string $queue, $data): bool
    {
        return $this->redis->lpush($queue, json_encode($data)) > 0;
    }

    /**
     * 队列弹出
     */
    public function pop(string $queue)
    {
        $data = $this->redis->rpop($queue);
        return $data ? json_decode($data, true) : null;
    }

    /**
     * 阻塞队列弹出
     */
    public function blockPop(string $queue, int $timeout = 0)
    {
        $result = $this->redis->brpop($queue, $timeout);
        return $result ? json_decode($result[1], true) : null;
    }

    /**
     * 发布消息
     */
    public function publish(string $channel, $message): int
    {
        return $this->redis->publish($channel, json_encode($message));
    }

    /**
     * 订阅消息
     */
    public function subscribe(array $channels, \Closure $callback): void
    {
        $this->redis->subscribe($channels, function($redis, $channel, $message) use ($callback) {
            $callback($channel, json_decode($message, true));
        });
    }
}
