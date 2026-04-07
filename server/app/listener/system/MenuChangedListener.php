<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\listener\system;

use think\facade\Cache;
use think\facade\Log;

/**
 * 菜单变更监听器
 *
 * 事件数据：
 * - action: string         变更类型 (create|update|delete|batchDelete|sort)
 * - menu_id: int|array|null 变更的菜单ID（batchDelete 时为数组）
 */
class MenuChangedListener
{
    public function handle(array $event): void
    {
        // 清除菜单缓存（标签化管理，tag clear 会清除所有 menu_tree:*、menu_options:* 键）
        Cache::tag('menu')->clear();

        Log::info('菜单已更新，缓存已清除', [
            'action'  => $event['action'] ?? '',
            'menu_id' => $event['menu_id'] ?? null,
        ]);
    }
}
