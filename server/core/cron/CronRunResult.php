<?php

declare(strict_types=1);

namespace core\cron;

/** 一次命令执行的结果（写 cron_job_logs 与回传手动执行结果都用它）。 */
final class CronRunResult
{
    public function __construct(
        public readonly bool $success,
        public readonly int $exitCode,
        public readonly string $output,
        public readonly string $error,
        public readonly int $durationMs,
        public readonly string $startedAt,
        public readonly string $finishedAt,
    ) {
    }
}
