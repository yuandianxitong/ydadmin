<?php
declare(strict_types=1);

namespace core\cache;

use think\facade\Cache;

/**
 * Repository 缓存能力 Trait
 *
 * 使用方式：在 Repository 中 use 此 Trait，并声明 $cacheTag 和 $cacheTTL 属性。
 */
trait CacheableRepository
{
    protected function cacheRemember(string $key, \Closure $callback, ?int $ttl = null): mixed
    {
        $ttl = $ttl ?? $this->cacheTTL ?? 3600;
        $tag = $this->cacheTag ?? 'default';
        return Cache::tag($tag)->remember($key, $callback, $ttl);
    }

    protected function cacheClear(): void
    {
        $tag = $this->cacheTag ?? 'default';
        Cache::tag($tag)->clear();
    }

    protected function cacheForget(string $key): void
    {
        Cache::delete($key);
    }

    /**
     * 公开清除缓存（供 Service 层调用）
     */
    public function clearCache(): void
    {
        $this->cacheClear();
    }
}
