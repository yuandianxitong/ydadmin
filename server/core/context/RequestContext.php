<?php

declare(strict_types=1);

namespace core\context;

use support\Context;

/**
 * 请求级上下文：trace id 与当前操作管理员。存放在 support\Context，webman 每个请求结束时销毁。
 */
final class RequestContext
{
    /**
     * 入站 trace 会写进每一行日志，必须校验字符集与长度，防止换行注入伪造日志。
     * 结尾用 \z 而不是 $：$ 允许末尾多一个换行，会让 "abcdefgh\n" 通过校验。
     */
    public const TRACE_PATTERN = '/^[A-Za-z0-9._-]{8,128}\z/';

    private const K_TRACE  = 'ctx.trace_id';
    private const K_ACTING = 'ctx.acting_user';

    public static function initTrace(?string $inbound): string
    {
        $trace = ($inbound !== null && preg_match(self::TRACE_PATTERN, $inbound) === 1)
            ? $inbound
            : bin2hex(random_bytes(16));
        Context::set(self::K_TRACE, $trace);

        return $trace;
    }

    public static function traceId(): string
    {
        return (string) (Context::get(self::K_TRACE) ?? '');
    }

    public static function setActingUser(int $id): void
    {
        Context::set(self::K_ACTING, $id);
    }

    public static function actingUser(): int
    {
        return (int) (Context::get(self::K_ACTING) ?? 0);
    }
}
