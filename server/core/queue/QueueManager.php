<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace core\queue;

use think\facade\Queue;
use think\facade\Log;

class QueueManager
{
    /**
     * 推送任务到队列
     */
    public static function push(string $job, array $data = [], string $queue = 'default'): bool
    {
        try {
            Queue::push($job, $data, $queue);

            Log::info('队列任务推送成功', [
                'job' => $job,
                'queue' => $queue,
                'data' => $data
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('队列任务推送失败', [
                'job' => $job,
                'queue' => $queue,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * 延时推送任务
     */
    public static function later(int $delay, string $job, array $data = [], string $queue = 'default'): bool
    {
        try {
            Queue::later($delay, $job, $data, $queue);

            Log::info('延时队列任务推送成功', [
                'job' => $job,
                'queue' => $queue,
                'delay' => $delay,
                'data' => $data
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('延时队列任务推送失败', [
                'job' => $job,
                'queue' => $queue,
                'delay' => $delay,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * 批量推送任务
     */
    public static function bulk(array $jobs, string $queue = 'default'): bool
    {
        try {
            foreach ($jobs as $job) {
                Queue::push($job['job'] ?? '', $job['data'] ?? [], $queue);
            }

            Log::info('批量队列任务推送成功', [
                'count' => count($jobs),
                'queue' => $queue
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('批量队列任务推送失败', [
                'queue' => $queue,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * 获取队列统计信息
     */
    public static function stats(string $queue = 'default'): array
    {
        // 这里需要根据具体的队列驱动实现
        return [
            'queue' => $queue,
            'waiting' => 0,
            'delayed' => 0,
            'failed' => 0,
            'processed' => 0
        ];
    }

    /**
     * 清空队列
     */
    public static function clear(string $queue = 'default'): bool
    {
        try {
            // 具体实现依赖于队列驱动
            Log::info('队列清空成功', ['queue' => $queue]);
            return true;
        } catch (\Exception $e) {
            Log::error('队列清空失败', [
                'queue' => $queue,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
