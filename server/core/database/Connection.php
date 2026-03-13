<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace core\database;

use think\facade\Db;
use think\facade\Log;

class Connection
{
    protected static array $connections = [];

    /**
     * 获取数据库连接
     */
    public static function get(string $name = 'default'): \think\db\Query
    {
        if (!isset(self::$connections[$name])) {
            self::$connections[$name] = Db::connect($name);
        }

        return self::$connections[$name];
    }

    /**
     * 执行事务
     */
    public static function transaction(\Closure $callback, string $connection = 'default')
    {
        $db = self::get($connection);

        $db->startTrans();
        try {
            $result = $callback($db);
            $db->commit();
            return $result;
        } catch (\Exception $e) {
            $db->rollback();
            Log::error('事务执行失败: ' . $e->getMessage(), [
                'connection' => $connection,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * 执行SQL查询并记录慢查询
     */
    public static function query(string $sql, array $bind = [], string $connection = 'default')
    {
        $startTime = microtime(true);

        try {
            $result = self::get($connection)->query($sql, $bind);

            $executionTime = microtime(true) - $startTime;

            // 记录慢查询
            if ($executionTime > 1.0) {
                Log::warning('慢查询检测', [
                    'sql' => $sql,
                    'bind' => $bind,
                    'execution_time' => $executionTime,
                    'connection' => $connection
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('SQL执行失败', [
                'sql' => $sql,
                'bind' => $bind,
                'error' => $e->getMessage(),
                'connection' => $connection
            ]);
            throw $e;
        }
    }

    /**
     * 获取表结构信息
     */
    public static function getTableSchema(string $table, string $connection = 'default'): array
    {
        $sql = "SHOW FULL COLUMNS FROM `{$table}`";
        return self::query($sql, [], $connection);
    }

    /**
     * 检查表是否存在
     */
    public static function tableExists(string $table, string $connection = 'default'): bool
    {
        $sql = "SHOW TABLES LIKE '{$table}'";
        $result = self::query($sql, [], $connection);
        return !empty($result);
    }

    /**
     * 获取数据库大小
     */
    public static function getDatabaseSize(string $database = '', string $connection = 'default'): float
    {
        if (empty($database)) {
            $database = config("database.connections.{$connection}.database");
        }

        $sql = "SELECT SUM(data_length + index_length) as size
                FROM information_schema.tables
                WHERE table_schema = '{$database}'";

        $result = self::query($sql, [], $connection);
        return $result[0]['size'] ?? 0;
    }

    /**
     * 优化表
     */
    public static function optimizeTable(string $table, string $connection = 'default'): bool
    {
        try {
            $sql = "OPTIMIZE TABLE `{$table}`";
            self::query($sql, [], $connection);
            return true;
        } catch (\Exception $e) {
            Log::error("表优化失败: {$table}", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * 分析表
     */
    public static function analyzeTable(string $table, string $connection = 'default'): bool
    {
        try {
            $sql = "ANALYZE TABLE `{$table}`";
            self::query($sql, [], $connection);
            return true;
        } catch (\Exception $e) {
            Log::error("表分析失败: {$table}", ['error' => $e->getMessage()]);
            return false;
        }
    }
}
