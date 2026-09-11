<?php

declare(strict_types=1);

namespace core\permission;

interface PermissionCheckerInterface
{
    /** 超级管理员无视权限点，也可访问未标注注解的方法。 */
    public function isSuperAdmin(int $adminId): bool;

    /** 管理员是否拥有该权限点。 */
    public function check(int $adminId, string $permission): bool;
}
