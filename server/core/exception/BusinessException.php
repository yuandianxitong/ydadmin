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

class BusinessException extends Exception
{
    protected $data = [];

    public function __construct(string $message = '', int $code = 400, $data = [], Exception $previous = null)
    {
        $this->data = $data;
        parent::__construct($message ?: lang('auth.business_error'), $code, $previous);
    }

    public function getData()
    {
        return $this->data;
    }

    public function setData($data): self
    {
        $this->data = $data;
        return $this;
    }
}
