<?php

declare(strict_types=1);

namespace core\exception;

/** 无权访问：body.code 403。未给消息时按当前请求的 locale 取 auth.forbidden。 */
class ForbiddenException extends BusinessException
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? lang('auth.forbidden'), 403);
    }
}
