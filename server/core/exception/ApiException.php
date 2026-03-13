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

class ApiException extends Exception
{
    protected $httpCode;
    protected $data = [];

    public function __construct(string $message = '', int $code = 500, int $httpCode = 500, $data = [], Exception $previous = null)
    {
        $this->httpCode = $httpCode;
        $this->data = $data;
        parent::__construct($message ?: lang('auth.api_error'), $code, $previous);
    }

    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    public function getData()
    {
        return $this->data;
    }
}
