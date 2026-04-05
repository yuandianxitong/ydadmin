<?php
declare(strict_types=1);

namespace app\job;

use app\repository\message\MessageLogRepository;
use think\queue\Job;
use think\facade\Log;

class MessageSendJob
{
    public function fire(Job $job, array $data): void
    {
        try {
            $channel = $data['channel'];
            $receiver = $data['receiver'];
            $templateId = $data['template_id'];
            $variables = $data['variables'];
            $extra = $data['extra'] ?? [];
            $logId = $data['log_id'];

            $channelInstance = $this->resolveChannel($channel);
            $result = $channelInstance->send($receiver, $templateId, $variables, $extra);

            /** @var MessageLogRepository $logRepository */
            $logRepository = app(MessageLogRepository::class);
            $logRepository->updateLogResult($logId, [
                'status'    => $result['success'] ? 1 : 2,
                'error_msg' => $result['error'] ?? '',
                'sent_at'   => date('Y-m-d H:i:s'),
                'content'   => json_encode($variables, JSON_UNESCAPED_UNICODE),
            ]);

            if (!$result['success']) {
                Log::warning("消息发送失败 [{$channel}]: " . ($result['error'] ?? ''));
            }

            $job->delete();
        } catch (\Throwable $e) {
            Log::error('消息发送 Job 异常', [
                'error' => $e->getMessage(),
                'data'  => $data,
            ]);
            if ($job->attempts() >= 3) {
                $job->delete();
            } else {
                $job->release(30);
            }
        }
    }

    private function resolveChannel(string $channel): object
    {
        $map = [
            'sms'              => \app\service\message\channel\SmsChannel::class,
            'wechat_official'  => \app\service\message\channel\WechatOfficialChannel::class,
            'wechat_mini'      => \app\service\message\channel\WechatMiniChannel::class,
        ];
        $class = $map[$channel] ?? null;
        if (!$class) {
            throw new \RuntimeException("未知消息通道: {$channel}");
        }
        return app($class);
    }
}
