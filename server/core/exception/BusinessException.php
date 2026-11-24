<?php

declare(strict_types=1);

namespace core\exception;

/** 业务异常：message 原样返回给客户端，code 即 body.code。 */
class BusinessException extends \RuntimeException
{
    public function __construct(string $message, int $code = 400, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public static function pluginRuntimeError(string $pluginCode, string $hook, ?\Throwable $previous = null): self
    {
        $msg = "插件 [{$pluginCode}] 运行 [{$hook}] 失败" . ($previous !== null ? '：' . $previous->getMessage() : '');

        return new self($msg, 50001, $previous);
    }
}
