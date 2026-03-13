<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace core\base;

use think\facade\Log;
use think\facade\Cache;
use think\facade\Event;
use core\exception\BusinessException;

abstract class Service
{
    protected array $config = [];

    /**
     * 依赖解析缓存（进程级）
     */
    private static array $resolveCache = [];

    public function __construct()
    {
        $this->resolveDependencies();
        $this->initialize();
    }

    /**
     * 自动从容器解析子类声明的类型属性
     *
     * 子类只需声明带类型的 protected 属性，基类会自动从容器注入：
     * ```php
     * class AdminService extends Service
     * {
     *     protected AdminRepository $adminRepository; // 自动注入
     *     protected TokenManager $tokenManager;        // 自动注入
     * }
     * ```
     */
    private function resolveDependencies(): void
    {
        $class = static::class;

        if (!isset(self::$resolveCache[$class])) {
            $props = [];
            $ref = new \ReflectionClass($class);
            foreach ($ref->getProperties(\ReflectionProperty::IS_PROTECTED) as $prop) {
                if ($prop->getDeclaringClass()->getName() === self::class) continue;
                $type = $prop->getType();
                if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) continue;
                $props[] = $prop;
            }
            self::$resolveCache[$class] = $props;
        }

        foreach (self::$resolveCache[$class] as $prop) {
            if ($prop->isInitialized($this)) continue;
            try {
                $prop->setValue($this, app()->make($prop->getType()->getName()));
            } catch (\Throwable) {
                // 无法解析的属性保持未初始化，开发者可在 initialize() 中手动设置
            }
        }
    }

    /**
     * 初始化方法
     */
    protected function initialize(): void
    {
        // 子类可重写此方法
    }

    /**
     * 记录日志
     */
    protected function log(string $message, array $context = [], string $level = 'info'): void
    {
        Log::record($message, $level, $context);
    }

    /**
     * 抛出业务异常
     */
    protected function throwBusinessException(string $message, int $code = 400): void
    {
        throw new BusinessException($message, $code);
    }

    /**
     * 触发事件
     */
    protected function trigger(string $event, $data = null): void
    {
        Event::trigger($event, $data);
    }

    /**
     * 缓存操作
     */
    protected function cache(string $key, $value = null, int $expire = 3600)
    {
        if ($value === null) {
            return Cache::get($key);
        }

        return Cache::set($key, $value, $expire);
    }

    /**
     * 删除缓存
     */
    protected function forgetCache(string $key): bool
    {
        return Cache::delete($key);
    }

    /**
     * 缓存标签
     */
    protected function cacheTag(string $tag): \think\cache\TagSet
    {
        return Cache::tag($tag);
    }
}
