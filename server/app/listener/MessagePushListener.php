<?php
declare(strict_types=1);

namespace app\listener;

use think\facade\Log;

/**
 * 消息推送监听器
 *
 * 根据用户设备类型选择推送通道：
 * - 小程序端：微信订阅消息
 * - App 端：uni-push（个推）
 * - H5 端：数据库记录（页面内通过接口拉取）
 */
class MessagePushListener
{
    /**
     * 处理消息推送事件
     *
     * @param array $data ['user_id' => int, 'title' => string, 'content' => string, 'type' => string, 'extra' => array]
     */
    public function handle(array $data): void
    {
        $userId = $data['user_id'] ?? 0;
        $title = $data['title'] ?? '';
        $content = $data['content'] ?? '';
        $type = $data['type'] ?? 'system';
        $extra = $data['extra'] ?? [];

        if (empty($userId) || empty($title)) {
            Log::warning('MessagePush: missing user_id or title', $data);
            return;
        }

        try {
            // 获取用户的设备信息，决定推送通道
            $channels = $this->resolveChannels($userId);

            foreach ($channels as $channel) {
                $this->pushToChannel($channel, [
                    'user_id' => $userId,
                    'title'   => $title,
                    'content' => $content,
                    'type'    => $type,
                    'extra'   => $extra,
                ]);
            }

            Log::info("MessagePush: sent to user {$userId} via " . implode(',', $channels));
        } catch (\Throwable $e) {
            Log::error('MessagePush failed: ' . $e->getMessage(), [
                'user_id' => $userId,
                'error'   => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * 根据用户决定推送通道
     *
     * @return string[] 通道列表
     */
    protected function resolveChannels(int $userId): array // @phpstan-ignore-line
    {
        // 默认通过数据库记录（所有平台都可以通过接口拉取）
        $channels = ['database'];

        // TODO: 查询用户最近登录的设备类型
        // 如果有小程序 openid -> 加入 wechat_subscribe
        // 如果有 App push token -> 加入 uni_push
        // 示例:
        // $userDevice = UserDeviceRepository::getLatest($userId);
        // if ($userDevice && $userDevice->platform === 'mp-weixin' && $userDevice->openid) {
        //     $channels[] = 'wechat_subscribe';
        // }
        // if ($userDevice && $userDevice->platform === 'app' && $userDevice->push_token) {
        //     $channels[] = 'uni_push';
        // }

        return $channels;
    }

    /**
     * 推送到指定通道
     */
    protected function pushToChannel(string $channel, array $message): void
    {
        match ($channel) {
            'database'          => $this->pushToDatabase($message),
            'wechat_subscribe'  => $this->pushToWechatSubscribe($message),
            'uni_push'          => $this->pushToUniPush($message),
            default             => Log::warning("MessagePush: unknown channel {$channel}"),
        };
    }

    /**
     * 数据库记录（所有平台的兜底方案）
     */
    protected function pushToDatabase(array $message): void
    {
        // 消息已在 Service 层创建到数据库，此处不需要重复写入
        // 如果需要额外的推送记录表，可以在此处写入
        Log::debug('MessagePush: recorded to database for user ' . $message['user_id']);
    }

    /**
     * 微信订阅消息推送
     *
     * 需要配置:
     * - 小程序 appid/secret
     * - 订阅消息模板 ID
     * - 用户的 openid
     */
    protected function pushToWechatSubscribe(array $message): void
    {
        $userId = (int) ($message['user_id'] ?? 0);
        if (!$userId) {
            return;
        }

        $user = app(\app\repository\user\UserRepository::class)->findModel($userId);
        if (!$user || empty($user->mini_openid)) {
            Log::debug("MessagePush: user {$userId} has no mini_openid, skip wechat subscribe");
            return;
        }

        $templateCode = $message['extra']['template_code'] ?? 'notification';
        $receivers = ['mini_openid' => $user->mini_openid];
        $data = [
            'title'   => $message['title'] ?? '',
            'content' => $message['content'] ?? '',
        ];

        app(\app\service\message\MessageService::class)->trySend($templateCode, $receivers, $data);
    }

    /**
     * UniPush（个推）推送
     *
     * 需要配置:
     * - 个推 AppID/AppKey/MasterSecret
     * - 用户的 push clientId (cid)
     */
    protected function pushToUniPush(array $message): void
    {
        // TODO: 实现 uni-push 推送
        // 1. 获取用户的 push token (cid)
        // 2. 调用个推 REST API 发送推送
        // UniPushService::pushToSingle($cid, $message['title'], $message['content'], $message['extra']);
        Log::info('MessagePush: uni-push (not implemented yet)', $message);
    }
}
