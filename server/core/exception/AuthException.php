<?php

declare(strict_types=1);

namespace core\exception;

class AuthException extends BusinessException
{
    public function __construct(string $message = '未登录或登录已过期')
    {
        parent::__construct($message, 401);
    }
}
