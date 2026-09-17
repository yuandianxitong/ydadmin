<?php

declare(strict_types=1);

namespace app\service\message;

use app\repository\message\MessageLogRepository;
use app\repository\message\MessageTemplateRepository;
use app\repository\message\UserNotificationRepository;
use app\repository\user\UserRepository;
use core\base\Service;
use core\message\exception\MessageDefiniteFailure;
use core\message\TemplateRenderer;
use core\queue\QueueDispatcher;
use DI\Attribute\Inject;
use support\Log;

/**
 * 业务发消息的唯一入口（M6b spec §4.3）。
 *
 * - 站内信同步写 user_notifications，再写一条 status=1 的 message_logs，两行放在同一事务里。
 * - 外发通道先写 status=0 的日志（receiver 遮蔽），再投递 message-send，由 MessageSendConsumer 真正发送。
 * - 调用方一律经 afterCommit 调用，本方法不在外层事务里；外发日志行不开事务，写完即已提交，消费者锁读得到。
 * - 永不抛出：任何异常只记 error 日志（code + 异常类名），异常消息可能带 SQL 绑定值（手机号、openid）。
 *
 * 落库文案（error_msg、站内信 type 缺省值）固定，不随请求语言变化。
 */
class MessageService extends Service
{
    /** 站内信展示分类：模板 code → user_notifications.type，未列出的一律 system（uniapp 按它选图标与标签） */
    public const SITE_TYPES = ['payment_success' => 'payment'];

    public const QUEUE = 'message-send';

    public const ERROR_MISSING_MAPPING = '未配置字段映射';

    private const SITE_TYPE_DEFAULT = 'system';

    private const SITE_TITLE_MAX = 100;

    private const SITE_CONTENT_MAX = 500;

    private const BIZ_ID_MAX = 64;

    private const ERROR_MSG_MAX = 255;

    #[Inject]
    protected MessageTemplateRepository $templates;

    #[Inject]
    protected MessageLogRepository $logs;

    #[Inject]
    protected UserNotificationRepository $notifications;

    #[Inject]
    protected UserRepository $users;

    #[Inject]
    protected TemplateRenderer $renderer;

    #[Inject]
    protected QueueDispatcher $queue;

    /** @param array<string, mixed> $vars */
    public function sendToUser(int $userId, string $code, array $vars, string $bizId = ''): void
    {
        try {
            $template = $this->templates->findActiveByCode($code);
            if ($template === null) {
                Log::warning('消息模板不存在或已停用，跳过发送', ['code' => $code]);

                return;
            }
            $user = $this->users->find($userId);
            if ($user === null) {
                return;
            }

            if ((int) ($template['site_enabled'] ?? 0) === 1) {
                $this->sendSite($template, $userId, $vars, $bizId);
            }
            foreach (MessageChannelRegistry::EXTERNAL_CHANNELS as $channel => $fields) {
                $this->enqueue($template, $user, $channel, $fields, $vars);
            }
        } catch (\Throwable $e) {
            Log::error('发送消息失败', ['code' => $code, 'exception' => $e::class]);
        }
    }

    /**
     * @param array<string, mixed> $template
     * @param array<string, mixed> $vars
     */
    private function sendSite(array $template, int $userId, array $vars, string $bizId): void
    {
        $code = (string) $template['code'];
        $log = [
            'template_id'   => (int) $template['id'],
            'template_code' => $code,
            'channel'       => MessageLogRepository::CHANNEL_SITE,
            'user_id'       => $userId,
            'receiver'      => ReceiverMask::mask(MessageLogRepository::CHANNEL_SITE, "user#{$userId}"),
            'variables'     => $vars,
        ];

        try {
            $title = mb_substr($this->renderer->render((string) $template['site_title'], $vars), 0, self::SITE_TITLE_MAX);
            $content = mb_substr($this->renderer->render((string) $template['site_content'], $vars), 0, self::SITE_CONTENT_MAX);
        } catch (MessageDefiniteFailure $e) {
            $this->logs->create($log + [
                'status'    => MessageLogRepository::STATUS_FAILED,
                'error_msg' => mb_substr($e->getMessage(), 0, self::ERROR_MSG_MAX),
                'attempts'  => 1,
            ]);

            return;
        }

        $this->runInTransaction(function () use ($log, $code, $userId, $title, $content, $bizId): void {
            $this->notifications->create([
                'user_id' => $userId,
                'title'   => $title,
                'content' => $content,
                'type'    => self::SITE_TYPES[$code] ?? self::SITE_TYPE_DEFAULT,
                'biz_id'  => mb_substr($bizId, 0, self::BIZ_ID_MAX),
                'extra'   => ['template_code' => $code],
            ]);
            $this->logs->create($log + [
                'content'   => json_encode(['title' => $title, 'content' => $content], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'status'    => MessageLogRepository::STATUS_SUCCESS,
                'error_msg' => '',
                'attempts'  => 1,
                'sent_at'   => date('Y-m-d H:i:s'),
            ]);
        });
    }

    /**
     * @param array<string, mixed> $template
     * @param array<string, mixed> $user
     * @param array{enabled: string, template_id: string, data: ?string, link: ?string, link_key: ?string, receiver: string} $fields
     * @param array<string, mixed> $vars
     */
    private function enqueue(array $template, array $user, string $channel, array $fields, array $vars): void
    {
        if ((int) ($template[$fields['enabled']] ?? 0) !== 1 || (string) ($template[$fields['template_id']] ?? '') === '') {
            return;
        }
        $receiver = (string) ($user[$fields['receiver']] ?? '');
        if ($receiver === '') {
            return;
        }

        $log = [
            'template_id'   => (int) $template['id'],
            'template_code' => (string) $template['code'],
            'channel'       => $channel,
            'user_id'       => (int) $user['id'],
            'receiver'      => ReceiverMask::mask($channel, $receiver),
            'variables'     => $vars,
        ];

        $mapping = $fields['data'] === null ? null : ($template[$fields['data']] ?? null);
        if ($fields['data'] !== null && (!is_array($mapping) || $mapping === [])) {
            $this->logs->create($log + ['status' => MessageLogRepository::STATUS_FAILED, 'error_msg' => self::ERROR_MISSING_MAPPING]);

            return;
        }

        $created = $this->logs->create($log + ['status' => MessageLogRepository::STATUS_PENDING, 'error_msg' => '']);
        $logId = (int) $created['id'];
        try {
            $this->queue->dispatch(self::QUEUE, ['log_id' => $logId]);
        } catch (\Throwable $e) {
            Log::error('消息投递队列失败', ['code' => $log['template_code'], 'channel' => $channel, 'exception' => $e::class]);
            $this->logs->finishIfPending($logId, MessageLogRepository::STATUS_FAILED, '', 'dispatch failed: ' . $e::class);
        }
    }
}
