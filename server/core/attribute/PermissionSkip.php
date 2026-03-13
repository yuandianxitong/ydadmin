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
 * 跳过权限检查注解
 *
 * 在控制器方法上使用，声明该方法不需要权限校验。
 * 适用于 options、tree、个人操作等不需要独立权限的接口。
 *
 * 示例：
 *   #[PermissionSkip]
 *   public function options(): Response { ... }
 */
#[Attribute(Attribute::TARGET_METHOD)]
class PermissionSkip
{
}
