<?php
declare(strict_types=1);

namespace core\ai;

class AiClientException extends \RuntimeException
{
    /**
     * @param string $errorCode 引擎错误码（见 Ai 仓库 docs/protocol/engine-protocol.md），非引擎来源为空串
     * @param string $requestId 请求追踪 ID（req_*），用于跨系统排查
     */
    public function __construct(
        string $message,
        protected string $errorCode = '',
        protected string $requestId = '',
    ) {
        parent::__construct($message);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getRequestId(): string
    {
        return $this->requestId;
    }
}
