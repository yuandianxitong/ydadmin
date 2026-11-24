<?php

declare(strict_types=1);

namespace core\plugin;

use core\exception\BusinessException;

/**
 * 触发 workerman master 的滚动 reload。
 *
 * 固定用 SIGUSR1（非优雅、有 stop_timeout 兜底），不能改 SIGUSR2：优雅分支要等连接数清零且没有 SIGKILL 兜底，
 * keep-alive 长连接会让 master 卡在第一个 worker 上，整池永远不换新。代价是被替换的那个 worker 上
 * 超过 stop_timeout 的在途请求会被中断。
 */
final class ReloadSignal
{
    /**
     * @return int master 进程 pid
     * @throws BusinessException master 进程未运行（pid 文件缺失/pid 非法/进程已死）
     */
    public static function masterPid(): int
    {
        $file = (string) config('server.pid_file');
        $pid = is_file($file) ? (int) trim((string) file_get_contents($file)) : 0;
        if ($pid <= 0 || !posix_kill($pid, 0)) {
            throw new BusinessException('未找到运行中的 master 进程，无法 reload：' . $file, 500);
        }
        return $pid;
    }

    /**
     * posix_kill() 返回 false 说明信号没发出去（典型是 EPERM），必须抛错；否则只会在 ReloadVerifier
     * 超时后报“指纹不一致”，把排查方向引偏。
     *
     * @throws BusinessException 信号发送失败（带上 master pid 供人工核对）
     */
    public static function trigger(): void
    {
        $pid = self::masterPid();
        if (!posix_kill($pid, SIGUSR1)) {
            throw new BusinessException(
                sprintf(
                    '向 master 进程发送 SIGUSR1 失败（pid=%d，errno=%d：%s），reload 未被触发',
                    $pid,
                    posix_get_last_error(),
                    posix_strerror(posix_get_last_error())
                ),
                500
            );
        }
    }
}
