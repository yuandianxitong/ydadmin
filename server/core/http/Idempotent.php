<?php

declare(strict_types=1);

namespace core\http;

#[\Attribute(\Attribute::TARGET_METHOD)]
final class Idempotent
{
    /** @param array<string, string> $permissions 按请求字段选择回放前必须重新检查的权限 */
    public function __construct(public readonly ?string $permissionField = null, public readonly array $permissions = [])
    {
    }
}
