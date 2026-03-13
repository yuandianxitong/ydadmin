<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\listener\user;

use think\facade\Log;

/**
 * 用户登录监听器
 *
 * 事件数据：
 * - user_id: int  用户ID
 *
 * 扩展点：可在此添加登录积分奖励、签到逻辑等
 */
class UserLoginListener
{
    public function handle(array $event): void
    {
        Log::info('用户登录', ['user_id' => $event['user_id']]);

        // TODO: 签到奖励
        // TODO: 登录统计
    }
}
