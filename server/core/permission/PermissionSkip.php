<?php

declare(strict_types=1);

namespace core\permission;

/** 登录即可访问、无需权限点的方法（如 auth/info、auth/logout）。未标注任何注解的方法默认拒绝。 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class PermissionSkip
{
}
