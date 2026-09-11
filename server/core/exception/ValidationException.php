<?php

declare(strict_types=1);

namespace core\exception;

/** 参数校验失败：body.code = 422，data.errors = {字段: 第一条错误消息}（与 TP8 版契约一致）。 */
class ValidationException extends BusinessException
{
    /** @param array<string, string> $errors */
    public function __construct(private readonly array $errors, string $message = '')
    {
        $first = $errors === [] ? '参数错误' : (string) reset($errors);
        parent::__construct($message !== '' ? $message : $first, 422);
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }
}
