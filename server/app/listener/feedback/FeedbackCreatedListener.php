<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\listener\feedback;

use app\service\message\MessageService;
use think\facade\Log;

/**
 * 用户提交反馈监听器
 *
 * 事件数据：
 * - feedback_id: int    反馈ID
 * - user_id: int        用户ID
 * - type: string        反馈类型
 */
class FeedbackCreatedListener
{
    public function __construct(
        protected MessageService $messageService,
    ) {
    }

    public function handle(array $event): void
    {
        Log::info('用户提交反馈', [
            'feedback_id' => $event['feedback_id'],
            'user_id'     => $event['user_id'],
            'type'        => $event['type'],
        ]);

        $this->messageService->sendToUser(
            (int) ($event['user_id'] ?? 0),
            'feedback_received',
        );
    }
}
