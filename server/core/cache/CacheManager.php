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
use think\cache\TagSet;

class CacheManager
{
    protected array $tags = [];

    /**
     * 设置缓存标签
     */
    public function tag(string $tag): self
    {
        $this->tags[] = $tag;
        return $this;
    }

    /**
     * 获取缓存
     */
    public function get(string $key, $default = null)
    {
        if (!empty($this->tags)) {
            return Cache::tag($this->tags)->get($key, $default);
        }

        return Cache::get($key, $default);
    }

    /**
     * 设置缓存
     */
    public function set(string $key, $value, int $expire = 3600): bool
    {
        if (!empty($this->tags)) {
            return Cache::tag($this->tags)->set($key, $value, $expire);
        }

        return Cache::set($key, $value, $expire);
    }

    /**
     * 删除缓存
     */
    public function delete(string $key): bool
    {
        if (!empty($this->tags)) {
            return Cache::tag($this->tags)->delete($key);
        }

        return Cache::delete($key);
    }

    /**
     * 清除标签缓存
     */
    public function clear(?string $tag = null): bool
    {
        if ($tag) {
            return Cache::tag($tag)->clear();
        }

        if (!empty($this->tags)) {
            foreach ($this->tags as $tagName) {
                Cache::tag($tagName)->clear();
            }
            return true;
        }

        return Cache::clear();
    }

    /**
     * 记住缓存
     */
    public function remember(string $key, \Closure $callback, int $expire = 3600)
    {
        if (!empty($this->tags)) {
            return Cache::tag($this->tags)->remember($key, $callback, $expire);
        }

        return Cache::remember($key, $callback, $expire);
    }

    /**
     * 检查缓存是否存在
     */
    public function has(string $key): bool
    {
        if (!empty($this->tags)) {
            return Cache::tag($this->tags)->has($key);
        }

        return Cache::has($key);
    }

    /**
     * 缓存递增
     */
    public function inc(string $key, int $step = 1): int
    {
        return Cache::inc($key, $step);
    }

    /**
     * 缓存递减
     */
    public function dec(string $key, int $step = 1): int
    {
        return Cache::dec($key, $step);
    }

    /**
     * 重置标签
     */
    public function resetTags(): self
    {
        $this->tags = [];
        return $this;
    }
}
