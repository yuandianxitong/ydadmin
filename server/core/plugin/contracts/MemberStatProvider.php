<?php

declare(strict_types=1);

namespace core\plugin\contracts;

/**
 * 会员统计数提供者：插件在 plugin.json `member_stats.provider` 声明实现类。
 * 与 DiyWidgetHydrator 的边界：hydrator 是租户级发布时机（无当前用户），
 * 本契约是 C 端登录态运行时按会员计数（GET /api/user/member-stats 聚合分发调用）。
 */
interface MemberStatProvider
{
    /**
     * @param string[] $keys 被请求的本插件原始 key（不含 "<code>." 前缀）
     * @return array<string, int|string> key => 数值；未返回的键视为缺失（端上显示 0/-）
     */
    public function counts(int $tenantId, int $userId, array $keys): array;
}
