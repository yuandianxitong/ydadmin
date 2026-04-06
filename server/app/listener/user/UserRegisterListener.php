<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\listener\user;

use app\service\message\MessageService;
use think\facade\Log;

/**
 * 用户注册监听器
 *
 * 事件数据：
 * - user_id: int      用户ID
 * - channel: string   注册渠道：sms / miniapp（可选）
 */
class UserRegisterListener
{
    public function __construct(
        protected MessageService $messageService,
    ) {
    }

    public function handle(array $event): void
    {
        Log::info('新用户注册', [
            'user_id' => $event['user_id'],
            'channel' => $event['channel'] ?? 'sms',
        ]);

        $this->messageService->sendToUser(
            (int) ($event['user_id'] ?? 0),
            'user_register',
        );
    }
}
