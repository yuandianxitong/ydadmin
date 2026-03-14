<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace core\exception;

use Exception;

class ValidationException extends Exception
{
    protected $errors;

    public function __construct($errors, string $message = '', int $code = ErrorCode::VALIDATE_FAILED, Exception $previous = null)
    {
        $this->errors = is_array($errors) ? $errors : [$errors];
        parent::__construct($message ?: lang('auth.validation_error'), $code, $previous);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getFirstError(): string
    {
        return is_array($this->errors) ? reset($this->errors) : $this->errors;
    }
}
