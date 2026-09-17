<?php

declare(strict_types=1);

namespace app\service\message;

use app\repository\message\MessageLogRepository;
use app\repository\message\MessageTemplateRepository;
use app\repository\user\UserRepository;
use core\base\Service;
use core\message\ChannelMessage;
use core\message\exception\MessageDefiniteFailure;
use core\message\exception\MessageFailure;
use core\message\exception\MessageTransientFailure;
use core\message\TemplateRenderer;
use DI\Attribute\Inject;

/**
 * 外发消息的真正发送（M6b spec §4.4、设计决定 10），由 MessageSendConsumer 调用。
 *
 * - 锁读与状态判断在短事务里完成：只把 attempts+1，status 保持 0；网络调用在事务外，不拿着行锁等微信接口。
 * - 发送前重读模板与接收人：排队期间模板被停用、通道被关闭、会员解绑，都以当下为准。
 * - 结果用 finishIfPending（WHERE status=0）条件写回：并发重复投递时只有一个结果落库。
 * - 确定失败写 status=2 后返回；暂时失败重抛，交给 M3 按 max_attempts 重试；通道漏出的其它异常按暂时失败处理，
 *   消息只带类名（异常消息可能带 SQL 绑定值）。
 *
 * error_msg 的固定文案落库，不随请求语言变化。
 */
class MessageDeliveryService extends Service
{
    public const ERROR_TEMPLATE_UNAVAILABLE = '模板已停用或通道已关闭';

    public const ERROR_RECEIVER_MISSING = '接收人不存在';

    public const ERROR_EXHAUSTED_PREFIX = '重试耗尽：';

    private const ERROR_MSG_MAX = 255;

    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;

    #[Inject]
    protected MessageLogRepository $logs;

    #[Inject]
    protected MessageTemplateRepository $templates;

    #[Inject]
    protected UserRepository $users;

    #[Inject]
    protected MessageChannelRegistry $registry;

    #[Inject]
    protected TemplateRenderer $renderer;

    /** @throws MessageTransientFailure */
    public function deliver(int $logId): void
    {
        $log = $this->runInTransaction(function () use ($logId): ?array {
            $row = $this->logs->findForUpdate($logId);
            if ($row === null || (int) $row['status'] !== MessageLogRepository::STATUS_PENDING) {
                return null;
            }
            $this->logs->incrementAttempts($logId);

            return $row;
        });
        if ($log === null) {
            return;
        }

        $channel = (string) $log['channel'];
        $content = '';
        $receiver = '';
        try {
            [$message, $content] = $this->prepare($log);
            $receiver = $message->receiver;
            $this->registry->get($channel)->send($message);
        } catch (MessageDefiniteFailure $e) {
            $this->logs->finishIfPending($logId, MessageLogRepository::STATUS_FAILED, $content, self::errorMsg(self::scrub($e->getMessage(), $channel, $receiver)));

            return;
        } catch (MessageTransientFailure $e) {
            // 重抛的消息会进应用日志（同步消费失败 / 第 n 次消费失败）与 failed_jobs.error，先遮蔽接收人；
            // 不挂 previous，免得原消息经异常链落盘
            throw new MessageTransientFailure(self::scrub($e->getMessage(), $channel, $receiver));
        } catch (\Throwable $e) {
            throw new MessageTransientFailure('unexpected ' . $e::class);
        }

        $this->logs->finishIfPending($logId, MessageLogRepository::STATUS_SUCCESS, $content, '');
    }

    /**
     * M3 判定最后一次失败后调用：status=2、error_msg「重试耗尽：…」。暂时失败的那几次没写 content，
     * 这里重组一次写上（重组本身失败就留空），管理端日志页才看得到原本要发什么。
     */
    public function markExhausted(int $logId, \Throwable $e): void
    {
        $log = $this->logs->find($logId);
        if ($log === null || (int) $log['status'] !== MessageLogRepository::STATUS_PENDING) {
            return;
        }

        try {
            [, $content] = $this->prepare($log);
        } catch (\Throwable) {
            $content = '';
        }
        $reason = $e instanceof MessageFailure ? $e->getMessage() : $e::class;

        $this->logs->finishIfPending($logId, MessageLogRepository::STATUS_FAILED, $content, self::errorMsg(self::ERROR_EXHAUSTED_PREFIX . $reason));
    }

    /**
     * 重读模板与接收人并渲染。
     *
     * @param array<string, mixed> $log
     * @return array{ChannelMessage, string} 通道载荷与写进 content 的 JSON
     * @throws MessageDefiniteFailure
     */
    private function prepare(array $log): array
    {
        $channel = (string) $log['channel'];
        $fields = MessageChannelRegistry::EXTERNAL_CHANNELS[$channel] ?? throw new MessageDefiniteFailure('unknown channel');

        $template = $log['template_id'] === null ? null : $this->templates->find((int) $log['template_id']);
        if ($template === null
            || (int) $template['status'] !== MessageTemplateRepository::STATUS_ENABLED
            || (int) ($template[$fields['enabled']] ?? 0) !== 1
            || (string) ($template[$fields['template_id']] ?? '') === ''
        ) {
            throw new MessageDefiniteFailure(self::ERROR_TEMPLATE_UNAVAILABLE);
        }
        $mapping = [];
        if ($fields['data'] !== null) {
            $mapping = $template[$fields['data']] ?? null;
            if (!is_array($mapping) || $mapping === []) {
                throw new MessageDefiniteFailure(self::ERROR_TEMPLATE_UNAVAILABLE);
            }
        }

        $user = $log['user_id'] === null ? null : $this->users->find((int) $log['user_id']);
        $receiver = $user === null ? '' : (string) ($user[$fields['receiver']] ?? '');
        if ($receiver === '') {
            throw new MessageDefiniteFailure(self::ERROR_RECEIVER_MISSING);
        }

        $templateId = (string) $template[$fields['template_id']];
        $vars = is_array($log['variables'] ?? null) ? $log['variables'] : [];

        if ($fields['data'] === null || $fields['link'] === null || $fields['link_key'] === null) {
            $variableDefs = is_array($template['variables'] ?? null) ? $template['variables'] : [];
            $params = $this->renderer->smsParams($variableDefs, $vars);

            return [new ChannelMessage($receiver, $templateId, $params), (string) json_encode($params, self::JSON_FLAGS)];
        }

        $data = $this->renderer->wechatData($mapping, $vars);
        $link = $this->renderer->render((string) ($template[$fields['link']] ?? ''), $vars);

        return [
            new ChannelMessage($receiver, $templateId, $data, $link),
            (string) json_encode(['data' => $data, $fields['link_key'] => $link], self::JSON_FLAGS),
        ];
    }

    /** 通道异常消息里可能回显真实手机号 / openid（网关报错、微信 errmsg），写库与重抛前换成遮蔽值。 */
    private static function scrub(string $message, string $channel, string $receiver): string
    {
        return $receiver === '' ? $message : str_replace($receiver, ReceiverMask::mask($channel, $receiver), $message);
    }

    private static function errorMsg(string $message): string
    {
        return mb_substr($message, 0, self::ERROR_MSG_MAX);
    }
}
