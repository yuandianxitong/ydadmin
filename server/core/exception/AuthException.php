<?php

declare(strict_types=1);

namespace core\exception;

/** 未登录或登录失效：body.code 401。未给消息时按当前请求的 locale 取 auth.unauthenticated。 */
class AuthException extends BusinessException
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? lang('auth.unauthenticated'), 401);
    }
}
