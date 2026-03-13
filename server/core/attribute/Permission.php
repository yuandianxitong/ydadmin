<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace core\attribute;

use Attribute;

/**
 * 权限标识注解
 *
 * 在控制器方法上使用，声明该方法所需的权限标识。
 * AdminPermissionMiddleware 通过反射读取此注解进行权限校验。
 *
 * 命名规范：{模块}.{资源}.{操作}
 * 示例：
 *   #[Permission('system.admin.list')]
 *   #[Permission('system.role.create')]
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Permission
{
    public function __construct(
        public readonly string $value
    ) {}
}
