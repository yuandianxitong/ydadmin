<?php
declare(strict_types=1);

namespace app\service\message\channel;

use core\wechat\WechatManager;

class WechatOfficialChannel implements ChannelInterface
{
    public function send(string $receiver, string $templateId, array $data, array $extra = []): array
    {
        try {
            $app = WechatManager::officialAccount();
            $api = $app->getClient();

            $message = [
                'touser'      => $receiver,
                'template_id' => $templateId,
                'data'        => $data,
            ];

            if (!empty($extra['url'])) {
                $message['url'] = $extra['url'];
            }

            $response = $api->postJson('/cgi-bin/message/template/send', $message);
            $result = $response->toArray();

            if (($result['errcode'] ?? -1) !== 0) {
                return ['success' => false, 'error' => $result['errmsg'] ?? lang('business.send_failed')];
            }

            return ['success' => true, 'error' => ''];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
