<?php

declare(strict_types=1);

namespace core\plugin;

use support\Log;

/**
 * 每个 HTTP worker 启动后把自己的插件指纹写进 runtime/workers/<pid>.json，
 * 供 /health 展示和滚动 reload 判断所有存活 worker 是否已收敛。
 *
 * 静态属性是 worker 启动时写入、此后只读的进程级值，不随请求或租户变化。
 */
final class WorkerFingerprintRegistry
{
    private static string $current = '';

    private static string $bootedAt = '';

    public static function dir(): string
    {
        return runtime_path() . '/workers';
    }

    /**
     * 写失败一律抛异常：文件没写出去时 liveWorkers() 看不到这个 worker，saga 会超时回滚，
     * 不抛就一行日志都没有。进程级缓存只在文件写成功后更新。
     * 多个 worker 并发 mkdir() 可能因目录已存在返回 false，所以失败后再查一次 is_dir()。
     */
    public static function write(int $pid, string $fingerprint): void
    {
        $dir = self::dir();
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf(
                '[plugin-fingerprint] 创建 worker 注册目录失败：%s（%s）',
                $dir,
                error_get_last()['message'] ?? '未知错误'
            ));
        }

        $bootedAt = date('Y-m-d H:i:s');
        $path = $dir . '/' . $pid . '.json';
        $bytes = @file_put_contents($path, json_encode([
            'pid'         => $pid,
            'fingerprint' => $fingerprint,
            'booted_at'   => $bootedAt,
        ], JSON_UNESCAPED_UNICODE));

        if ($bytes === false) {
            throw new \RuntimeException(sprintf(
                '[plugin-fingerprint] 写入 worker 注册文件失败：%s（%s）',
                $path,
                error_get_last()['message'] ?? '未知错误'
            ));
        }

        self::$current = $fingerprint;
        self::$bootedAt = $bootedAt;
    }

    public static function currentFingerprint(): string
    {
        return self::$current;
    }

    public static function bootedAt(): string
    {
        return self::$bootedAt;
    }

    public static function forget(int $pid): void
    {
        @unlink(self::dir() . '/' . $pid . '.json');
    }

    /**
     * 存活 worker 及其指纹。
     *
     * 用 posix_kill($pid, 0) 判活：被 SIGKILL 的 worker 来不及删文件，这里顺手清掉。
     * 文件读不出或解析不了时不删、只记 warning 跳过：可能是正在写入的中间态，
     * 删掉会把一个活着的 worker 从判据里抹掉。
     *
     * @return array<int, array{pid: int, fingerprint: string, booted_at: string}>
     */
    public static function liveWorkers(): array
    {
        $out = [];
        foreach (glob(self::dir() . '/*.json') ?: [] as $file) {
            $content = @file_get_contents($file);
            if ($content === false) {
                Log::warning("[plugin-fingerprint] worker 注册文件不可读，已跳过（未删除）：{$file}");
                continue;
            }

            $row = json_decode($content, true);
            if (!is_array($row)) {
                Log::warning("[plugin-fingerprint] worker 注册文件 JSON 解析失败，已跳过（未删除）：{$file}");
                continue;
            }

            $pid = (int) ($row['pid'] ?? 0);
            if ($pid <= 0) {
                Log::warning("[plugin-fingerprint] worker 注册文件缺少合法 pid 字段，已跳过（未删除）：{$file}");
                continue;
            }

            if (!posix_kill($pid, 0)) {
                // 孤儿文件清理是常态，不记日志。
                @unlink($file);
                continue;
            }

            $out[] = [
                'pid'         => $pid,
                'fingerprint' => (string) ($row['fingerprint'] ?? ''),
                'booted_at'   => (string) ($row['booted_at'] ?? ''),
            ];
        }

        return $out;
    }
}
