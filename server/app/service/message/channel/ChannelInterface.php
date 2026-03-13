<?php
declare(strict_types=1);

namespace app\service\message\channel;

interface ChannelInterface
{
    /**
     * 发送消息
     * @param string $receiver 接收者（手机号/openid）
     * @param string $templateId 第三方平台模板ID
     * @param array  $data 模板变量
     * @param array  $extra 额外参数（url、page 等）
     * @return array ['success' => bool, 'error' => string]
     */
    public function send(string $receiver, string $templateId, array $data, array $extra = []): array;
}
