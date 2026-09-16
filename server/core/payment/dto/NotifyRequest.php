<?php

declare(strict_types=1);

namespace core\payment\dto;

/**
 * 回调原始输入。headers 的键一律小写；rawBody 是未经解析的原始请求体（微信验签与解密只认它）；
 * form 是表单参数（支付宝回调用）。
 */
final readonly class NotifyRequest
{
    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>  $form
     */
    public function __construct(
        public array $headers,
        public string $rawBody,
        public array $form,
    ) {
    }
}
