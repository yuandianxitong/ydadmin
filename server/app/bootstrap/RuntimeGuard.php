<?php

declare(strict_types=1);

namespace app\bootstrap;

use Webman\Bootstrap;
use Workerman\Worker;

/**
 * 启动检查：拒绝协程事件循环。
 *
 * ValidatorFactory 的 Translator 是进程级单例，locale 写在它身上（见该类注释）；
 * 协程并发下不同请求的 locale 会互相覆盖。改为按请求构造 Translator 之前，
 * 协程驱动一律不允许启动。
 */
class RuntimeGuard implements Bootstrap
{
    private const COROUTINE_DRIVERS = ['swoole', 'swow', 'fiber'];

    public static function start(?Worker $worker): void
    {
        self::assertSupported((string) config('server.event_loop', ''));
        self::assertSupported((string) config('process.webman.eventLoop', ''));
    }

    public static function assertSupported(string $eventLoop): void
    {
        $lower = strtolower($eventLoop);
        foreach (self::COROUTINE_DRIVERS as $driver) {
            if ($lower !== '' && str_contains($lower, $driver)) {
                throw new \RuntimeException(
                    "当前不支持协程事件循环（{$eventLoop}）：Translator 的 locale 为进程级单例，协程并发下会串，见 core/validation/ValidatorFactory.php"
                );
            }
        }
    }
}
