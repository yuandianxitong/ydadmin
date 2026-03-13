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
 * 系统配置变更监听器
 *
 * 事件数据：
 * - keys: array   变更的配置key列表
 * - group: string 变更的配置分组（可选）
 */
class ConfigChangedListener
{
    public function handle(array $event): void
    {
        // 清除配置缓存
        Cache::delete('system_configs');
        Cache::tag('config')->clear();

        Log::info('系统配置已更新，缓存已清除', [
            'keys'  => $event['keys'] ?? [],
            'group' => $event['group'] ?? '',
        ]);
    }
}
