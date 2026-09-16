<?php

declare(strict_types=1);

namespace core\payment;

use core\exception\BusinessException;
use core\payment\exception\PaymentException;

/**
 * 支付日志里描述异常的唯一写法：只有消息「按设计不含敏感数据」的异常才记消息。
 *
 * - PaymentException：驱动与 Service 自己拼的消息，约定不含私钥、密钥与回调原文（global constraints）；
 * - BusinessException：对外的 lang 文案；
 * - 其余一律只记类名与异常码：数据库异常（QueryException）的消息带 SQL 与绑定值，
 *   绑定值里就是回调原文（notify_data，含 openid 等），不能进日志。PDO / QueryException 的异常码是 SQLSTATE。
 */
final class ExceptionLogContext
{
    /** @return array{exception: class-string<\Throwable>, reason?: string, code?: int|string} */
    public static function of(\Throwable $e): array
    {
        if ($e instanceof PaymentException || $e instanceof BusinessException) {
            return ['exception' => $e::class, 'reason' => $e->getMessage()];
        }

        return ['exception' => $e::class, 'code' => $e->getCode()];
    }
}
