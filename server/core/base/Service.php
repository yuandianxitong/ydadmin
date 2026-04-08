<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace core\base;

use think\facade\Db;
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
            } catch (\Throwable $e) {
                // 无法解析的属性保持未初始化，开发者可在 initialize() 中手动设置。
                // 但底层异常（如表不存在、配置缺失）会被静默吞掉，
                // 调用方读到属性时只会看到 "must not be accessed before initialization"，
                // 难以定位真正的故障点。开 debug 时把原始异常落到 Log，便于排查。
                if (env('APP_DEBUG', false)) {
                    Log::warning(sprintf(
                        '[DI] 注入 %s::$%s 失败：%s（@ %s:%d）',
                        $class,
                        $prop->getName(),
                        $e->getMessage(),
                        $e->getFile(),
                        $e->getLine()
                    ));
                }
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
     * 从请求参数中解构分页参数
     *
     * 兼容两种命名：`page/limit`（优先）与 `page_no/page_size`（历史遗留）。
     * 后端 `Controller::getRequestData()` 已做双向归一化，这里只是一个语义更清晰的入口。
     *
     * @return array{0: int, 1: int} [page, limit]
     */
    protected function extractPagination(array $params, int $defaultLimit = 20, int $defaultPage = 1): array
    {
        $page = (int) ($params['page'] ?? $params['page_no'] ?? $defaultPage);
        $limit = (int) ($params['limit'] ?? $params['page_size'] ?? $defaultLimit);
        return [$page, $limit];
    }

    /**
     * 查找记录，不存在则抛出业务异常
     *
     * 替代所有 Service 中手写的 `if (!$entity) throw new BusinessException(...)` 样板。
     *
     * @param Repository $repo
     * @param int $id
     * @param string $langKey 业务异常的 lang() 键，默认 `business.record_not_found`
     * @return array Repository find() 返回的数组
     */
    protected function findOrFail(Repository $repo, int $id, string $langKey = 'business.record_not_found'): array
    {
        $entity = $repo->find($id);
        if (!$entity) {
            throw new BusinessException(lang($langKey));
        }
        return $entity;
    }

    /**
     * 在事务中执行回调
     *
     * 替代所有 `Db::startTrans(); try { ... commit(); } catch { rollback(); throw; }` 样板。
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    protected function runInTransaction(callable $callback): mixed
    {
        Db::startTrans();
        try {
            $result = $callback();
            Db::commit();
            return $result;
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
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
