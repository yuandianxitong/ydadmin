<?php

declare(strict_types=1);

namespace core\permission;

/** 控制器方法所需的权限点，值为 menus.permission（如 'system.admin.list'），与前端 v-has-perm 一致。 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class Permission
{
    public function __construct(public readonly string $code)
    {
    }
}
