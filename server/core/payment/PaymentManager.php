<?php

declare(strict_types=1);

namespace core\payment;

use core\contract\ConfigValueReader;
use core\payment\config\AlipayConfig;
use core\payment\config\WechatPayConfig;
use core\payment\driver\AlipayDriver;
use core\payment\driver\WechatPayDriver;
use core\payment\exception\PaymentConfigException;

/**
 * 支付渠道解析（M5b spec §3、§5.8、§6）。
 *
 * - 每次 gateway() 现读 payment 组配置、现 new 驱动，不缓存实例：常驻内存下缓存了驱动，管理员改了凭据
 *   就不会生效（1.x 的 PaymentManager 静态缓存正是这么出的问题）。配置缓存由 ConfigValueReader 的实现负责，
 *   写配置的路径会失效它，所以改支付配置不需要 reload。
 * - gateway() 不看 enabled 开关，只要求凭据齐全：关单、退款、对账、回调验签都要在渠道被关掉之后继续工作，
 *   否则在途订单与退款无人处理。开关只由下单方经 isEnabled() 判断。
 * - 构造期不读配置、不抛异常：服务可以放心 #[Inject] 本类（与短信工厂绑定不同，见 container.php）。
 * - PaymentConfigException 的消息只给日志（写明缺哪个键），调用方对外统一转成 payment.unavailable。
 */
final class PaymentManager implements GatewayResolver
{
    private const ALIPAY_REQUIRED = ['pay_alipay_app_id', 'pay_alipay_private_key', 'pay_alipay_public_key'];

    private const WECHAT_REQUIRED = [
        'pay_wechat_app_id',
        'pay_wechat_mch_id',
        'pay_wechat_api_v3_key',
        'pay_wechat_serial_no',
        'pay_wechat_private_key_path',
    ];

    public function __construct(private readonly ConfigValueReader $config)
    {
    }

    public function isEnabled(string $channel): bool
    {
        if (!in_array($channel, Channel::ALL, true)) {
            return false;
        }
        // boolean 类型的配置经 SystemConfig::convertValueByType() 转成了 bool；其它实现可能给 1 / '1'
        $value = $this->config->getConfigValue("pay_{$channel}_enabled", false);

        return $value === true || $value === 1 || $value === '1';
    }

    public function gateway(string $channel): PaymentGatewayInterface
    {
        return match ($channel) {
            Channel::ALIPAY => new AlipayDriver($this->alipayConfig()),
            Channel::WECHAT => new WechatPayDriver($this->wechatConfig()),
            default         => throw new PaymentConfigException("未知支付渠道：{$channel}"),
        };
    }

    private function alipayConfig(): AlipayConfig
    {
        $values = $this->requireAll(self::ALIPAY_REQUIRED);

        return new AlipayConfig(
            appId: $values['pay_alipay_app_id'],
            privateKey: $values['pay_alipay_private_key'],
            alipayPublicKey: $values['pay_alipay_public_key'],
            sandbox: $this->flag('pay_alipay_sandbox'),
            connectTimeout: $this->seconds('payment.connect_timeout', 5.0),
            timeout: $this->seconds('payment.timeout', 10.0),
        );
    }

    private function wechatConfig(): WechatPayConfig
    {
        $values = $this->requireAll(self::WECHAT_REQUIRED);
        $publicKeyId = $this->string('pay_wechat_public_key_id');
        $publicKey = $this->string('pay_wechat_public_key');
        if (($publicKeyId === '') !== ($publicKey === '')) {
            throw new PaymentConfigException('pay_wechat_public_key_id 与 pay_wechat_public_key 必须成对填写（都留空则使用平台证书模式）');
        }

        return new WechatPayConfig(
            appId: $values['pay_wechat_app_id'],
            mchId: $values['pay_wechat_mch_id'],
            apiV3Key: $values['pay_wechat_api_v3_key'],
            merchantSerialNo: $values['pay_wechat_serial_no'],
            privateKeyPath: $this->absolutePath($values['pay_wechat_private_key_path']),
            publicKeyId: $publicKeyId === '' ? null : $publicKeyId,
            publicKey: $publicKey === '' ? null : $publicKey,
            certCacheDir: (string) config('payment.wechat_cert_dir', runtime_path('cert/wechatpay')),
            certRefreshInterval: max(1, (int) config('payment.wechat_cert_refresh_interval', 60)),
            connectTimeout: $this->seconds('payment.connect_timeout', 5.0),
            timeout: $this->seconds('payment.timeout', 10.0),
        );
    }

    /**
     * @param list<string> $keys
     * @return array<string, string>
     */
    private function requireAll(array $keys): array
    {
        $values = [];
        $missing = [];
        foreach ($keys as $key) {
            $values[$key] = $this->string($key);
            if ($values[$key] === '') {
                $missing[] = $key;
            }
        }
        if ($missing !== []) {
            throw new PaymentConfigException('支付配置不全，缺少：' . implode('、', $missing));
        }

        return $values;
    }

    private function string(string $key): string
    {
        $value = $this->config->getConfigValue($key, '');

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function flag(string $key): bool
    {
        $value = $this->config->getConfigValue($key, false);

        return $value === true || $value === 1 || $value === '1';
    }

    /** 相对路径从 server/ 算起（spec §6）；驱动收到的恒为绝对路径。 */
    private function absolutePath(string $path): string
    {
        return str_starts_with($path, '/') ? $path : base_path($path);
    }

    /** Guzzle 的 0 是「无限等待」：常驻 worker 下一次挂死的外呼会永久占住一个 worker，≤ 0 一律回退默认值。 */
    private function seconds(string $configKey, float $default): float
    {
        $value = (float) config($configKey, $default);

        return $value > 0 ? $value : $default;
    }
}
