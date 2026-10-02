<?php

declare(strict_types=1);

namespace app\service\message;

use app\repository\message\MessageLogRepository;
use core\message\channel\EmailChannel;
use core\message\channel\SmsChannel;
use core\message\channel\WechatMiniChannel;
use core\message\channel\WechatOfficialChannel;
use core\message\ChannelInterface;
use support\Container;

/**
 * 外发通道注册表（M6b 设计决定 7）。站内信不是通道：MessageService 同步写库，不经这里。
 *
 * 每次 get() 都从容器取，不缓存实例：测试经 Container::set() 换掉通道类即生效。
 */
class MessageChannelRegistry
{
    /**
     * 外发通道在模板表与会员表上对应的列。MessageService 判定发不发、MessageDeliveryService 重读与渲染共用这一份，
     * 数组顺序就是 sendToUser() 的投递顺序。
     *
     * - data：字段映射列；null 表示不需要映射（短信按模板 variables 的 key 组参数）
     * - link / link_key：跳转地址所在列，以及写进 message_logs.content 时用的键名
     * - receiver：会员表上的真实接收人列
     *
     * content：邮件正文所在列。有这一列时，主题取 template_id 列，正文取 content 列，渲染后放进 ChannelMessage 的 link。
     *
     * @var array<string, array{enabled: string, template_id: string, data: ?string, link: ?string, link_key: ?string, receiver: string, content?: string}>
     */
    public const EXTERNAL_CHANNELS = [
        MessageLogRepository::CHANNEL_SMS => [
            'enabled'     => 'sms_enabled',
            'template_id' => 'sms_template_id',
            'data'        => null,
            'link'        => null,
            'link_key'    => null,
            'receiver'    => 'mobile',
        ],
        MessageLogRepository::CHANNEL_WECHAT_OFFICIAL => [
            'enabled'     => 'wechat_official_enabled',
            'template_id' => 'wechat_official_template_id',
            'data'        => 'wechat_official_data',
            'link'        => 'wechat_official_url',
            'link_key'    => 'url',
            'receiver'    => 'oa_openid',
        ],
        MessageLogRepository::CHANNEL_WECHAT_MINI => [
            'enabled'     => 'wechat_mini_enabled',
            'template_id' => 'wechat_mini_template_id',
            'data'        => 'wechat_mini_data',
            'link'        => 'wechat_mini_page',
            'link_key'    => 'page',
            'receiver'    => 'mini_openid',
        ],
        MessageLogRepository::CHANNEL_EMAIL => [
            'enabled'     => 'email_enabled',
            'template_id' => 'email_subject',
            'data'        => null,
            'link'        => null,
            'link_key'    => null,
            'receiver'    => 'email',
            'content'     => 'email_content',
        ],
    ];

    private const CLASSES = [
        MessageLogRepository::CHANNEL_SMS             => SmsChannel::class,
        MessageLogRepository::CHANNEL_WECHAT_OFFICIAL => WechatOfficialChannel::class,
        MessageLogRepository::CHANNEL_WECHAT_MINI     => WechatMiniChannel::class,
        MessageLogRepository::CHANNEL_EMAIL           => EmailChannel::class,
    ];

    /** @throws \InvalidArgumentException 不是外发通道 */
    public function get(string $channel): ChannelInterface
    {
        $class = self::CLASSES[$channel] ?? throw new \InvalidArgumentException("未知的消息通道：{$channel}");
        $instance = Container::get($class);
        if (!$instance instanceof ChannelInterface) {
            throw new \LogicException("消息通道 {$channel} 的实现没有实现 " . ChannelInterface::class);
        }

        return $instance;
    }
}
