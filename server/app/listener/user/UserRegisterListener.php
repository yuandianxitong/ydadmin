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
 * 用户注册监听器
 *
 * 事件数据：
 * - user_id: int      用户ID
 * - mobile: string    手机号（手机注册时）
 * - channel: string   注册渠道：sms / miniapp（可选）
 *
 * 扩展点：可在此添加发送欢迎消息、初始化用户数据等逻辑
 */
class UserRegisterListener
{
    public function handle(array $event): void
    {
        Log::info('新用户注册', [
            'user_id' => $event['user_id'],
            'channel' => $event['channel'] ?? 'sms',
        ]);

        // TODO: 发送欢迎消息
        // app()->make(MessageService::class)->send('welcome', ...);

        // TODO: 初始化用户积分/优惠券等
    }
}
