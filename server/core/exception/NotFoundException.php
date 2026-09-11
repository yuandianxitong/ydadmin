<?php

declare(strict_types=1);

namespace core\exception;

/**
 * 记录不存在（含数据权限范围外的记录，spec §5.3）：HTTP 200 + body.code 404。
 * 与未知路由区分——后者是 HTTP 404。
 */
class NotFoundException extends BusinessException
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? lang('messages.data_not_found'), 404);
    }
}
