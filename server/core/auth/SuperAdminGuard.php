<?php

declare(strict_types=1);

namespace core\auth;

use core\context\RequestContext;
use core\exception\BusinessException;
use core\permission\PermissionCheckerInterface;

/**
 * 「仅超管」写操作守卫（M1b 越权收口）。角色与菜单定义的是 RBAC 本身：非超管哪怕持有对应权限点，
 * 改角色的菜单 / 数据范围 / 状态、改菜单的权限码，都能绕开「只能在自己已有权限内授权」，所以这些写操作只许超管。
 * 当前管理员取 RequestContext::actingUser()；为 0（CLI、队列）时不受限。
 * 容器单例，没有实例状态（唯一的属性是注入的无状态依赖）。
 */
final readonly class SuperAdminGuard
{
    public function __construct(private PermissionCheckerInterface $checker)
    {
    }

    /** 当前管理员不是超管时抛 BusinessException（code 400，auth.super_admin_only）。 */
    public function assert(): void
    {
        $actor = RequestContext::actingUser();
        if ($actor > 0 && !$this->checker->isSuperAdmin($actor)) {
            throw new BusinessException(lang('auth.super_admin_only'));
        }
    }
}
