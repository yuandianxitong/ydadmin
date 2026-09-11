<?php

declare(strict_types=1);

namespace core\exception;

class ForbiddenException extends BusinessException
{
    public function __construct(string $message = '无权限访问')
    {
        parent::__construct($message, 403);
    }
}
