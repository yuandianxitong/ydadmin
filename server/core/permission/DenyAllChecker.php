<?php

declare(strict_types=1);

namespace core\permission;

/**
 * M0 的安全默认实现：RBAC（M1）落地之前，拒绝一切需要权限点的访问。
 * M1 在 config/container.php 改绑 core\auth\Permission 后删除本类。
 */
final class DenyAllChecker implements PermissionCheckerInterface
{
    public function isSuperAdmin(int $adminId): bool
    {
        return false;
    }

    public function check(int $adminId, string $permission): bool
    {
        return false;
    }
}
