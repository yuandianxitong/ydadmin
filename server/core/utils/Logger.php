<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace core\utils;

use think\facade\Log;

class Logger
{
    /**
     * 记录用户操作日志
     */
    public static function userAction(int $userId, string $action, string $description = '', array $data = []): void
    {
        self::info('用户操作', [
            'user_id' => $userId,
            'action' => $action,
            'description' => $description,
            'data' => $data,
            'ip' => request()->ip(),
            'user_agent' => request()->header('User-Agent')
        ]);
    }

    /**
     * 记录系统错误
     */
    public static function systemError(\Throwable $e, array $context = []): void
    {
        self::error('系统错误', array_merge([
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ], $context));
    }

    /**
     * 记录API访问日志
     */
    public static function apiAccess(string $api, array $params = [], float $executionTime = 0): void
    {
        self::info('API访问', [
            'api' => $api,
            'params' => $params,
            'execution_time' => $executionTime,
            'ip' => request()->ip(),
            'user_agent' => request()->header('User-Agent')
        ]);
    }

    /**
     * 记录数据库操作日志
     */
    public static function dbOperation(string $operation, string $table, array $data = []): void
    {
        self::info('数据库操作', [
            'operation' => $operation,
            'table' => $table,
            'data' => $data
        ]);
    }

    /**
     * 记录安全事件
     */
    public static function security(string $event, array $data = []): void
    {
        self::warning('安全事件', array_merge([
            'event' => $event,
            'ip' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
            'timestamp' => time()
        ], $data));
    }

    /**
     * 性能监控日志
     */
    public static function performance(string $module, float $executionTime, int $memoryUsage = 0): void
    {
        self::info('性能监控', [
            'module' => $module,
            'execution_time' => $executionTime,
            'memory_usage' => $memoryUsage,
            'peak_memory' => memory_get_peak_usage(true)
        ]);
    }

    /**
     * 基础日志方法
     */
    protected static function log(string $level, string $message, array $context = []): void
    {
        Log::record($message, $level, $context);
    }

    public static function emergency(string $message, array $context = []): void
    {
        self::log('emergency', $message, $context);
    }

    public static function alert(string $message, array $context = []): void
    {
        self::log('alert', $message, $context);
    }

    public static function critical(string $message, array $context = []): void
    {
        self::log('critical', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('error', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('warning', $message, $context);
    }

    public static function notice(string $message, array $context = []): void
    {
        self::log('notice', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('info', $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        self::log('debug', $message, $context);
    }
}
