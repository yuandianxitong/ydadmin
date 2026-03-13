<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace core\queue;

use think\queue\Job as ThinkJob;
use think\facade\Log;

abstract class Job
{
    protected array $data;
    protected int $maxTries = 3;
    protected int $timeout = 60;

    /**
     * 执行任务
     */
    abstract public function handle(): void;

    /**
     * 任务失败处理
     */
    public function failed(\Throwable $e): void
    {
        Log::error('队列任务执行失败', [
            'job' => static::class,
            'data' => $this->data,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }

    /**
     * 设置任务数据
     */
    public function setData(array $data): self
    {
        $this->data = $data;
        return $this;
    }

    /**
     * 获取任务数据
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * 获取最大重试次数
     */
    public function getMaxTries(): int
    {
        return $this->maxTries;
    }

    /**
     * 获取超时时间
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * 记录日志
     */
    protected function log(string $message, array $context = []): void
    {
        Log::info($message, array_merge([
            'job' => static::class,
            'data' => $this->data
        ], $context));
    }

    /**
     * 任务重试
     */
    protected function retry(int $delay = 0): void
    {
        if ($delay > 0) {
            QueueManager::later($delay, static::class, $this->data);
        } else {
            QueueManager::push(static::class, $this->data);
        }
    }
}
