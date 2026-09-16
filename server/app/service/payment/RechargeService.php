<?php

declare(strict_types=1);

namespace app\service\payment;

use app\repository\payment\PaymentOrderRepository;
use app\repository\user\UserRepository;
use core\base\Service;
use core\contract\ConfigValueReader;
use core\exception\BusinessException;
use core\payment\Channel;
use core\payment\GatewayResolver;
use core\payment\Money;
use core\payment\TradeType;
use DI\Attribute\Inject;
use support\Redis;

/**
 * 余额充值 → 支付订单（M5b spec §4、§5.1）。
 *
 * 顺序：限流 → 端 × 渠道矩阵（含 JSAPI openid）→ 渠道开关 → 未过期待支付单上限 → PaymentService::createOrder()。
 * X-Client-Type 缺省或不在矩阵里一律拒绝，不回退为 pc。
 *
 * appid：微信渠道按端解析（小程序 / 公众号 / 开放平台，其余端用 pay_wechat_app_id），空值回退 pay_wechat_app_id，
 * 经 createOrder() 写入订单并传给驱动；支付宝不涉及 appid。
 *
 * 容器单例，无实例态。
 */
class RechargeService extends Service
{
    /** X-Client-Type → channel → trade_type（spec §4）；没有的组合就是不支持 */
    private const MATRIX = [
        'pc'        => [Channel::WECHAT => TradeType::NATIVE, Channel::ALIPAY => TradeType::PAGE],
        'h5'        => [Channel::WECHAT => TradeType::H5, Channel::ALIPAY => TradeType::WAP],
        'app'       => [Channel::WECHAT => TradeType::APP, Channel::ALIPAY => TradeType::APP],
        'wechat_h5' => [Channel::WECHAT => TradeType::JSAPI],
        'miniapp'   => [Channel::WECHAT => TradeType::JSAPI],
    ];

    /** JSAPI 按端取 openid 列（M6 的微信登录负责写入） */
    private const OPENID_COLUMNS = [
        'wechat_h5' => 'oa_openid',
        'miniapp'   => 'mini_openid',
    ];

    /** 微信按端取 appid（M6a spec §7）：openid 与 appid 必须来自同一个公众号/小程序/开放平台应用；空值回退 pay_wechat_app_id */
    private const WECHAT_APP_ID_KEYS = [
        'miniapp'   => 'wechat_mini_app_id',
        'wechat_h5' => 'wechat_official_app_id',
        'pc'        => 'wechat_open_app_id',
    ];

    private const RATE_WINDOW = 60;

    /** INCR 与 EXPIRE 原子执行（与 SmsCodeService 同一段脚本）：分两条命令时进程在中间崩掉，计数键就永不过期 */
    private const INCR_WITH_TTL = "local n = redis.call('INCR', KEYS[1]) if n == 1 or redis.call('TTL', KEYS[1]) == -1 then redis.call('EXPIRE', KEYS[1], ARGV[1]) end return n";

    #[Inject]
    protected PaymentService $paymentService;

    #[Inject]
    protected GatewayResolver $gateways;

    #[Inject]
    protected UserRepository $userRepository;

    #[Inject]
    protected PaymentOrderRepository $orders;

    #[Inject]
    protected ConfigValueReader $config;

    /**
     * @param string $amount     校验后的元字符串（最多两位小数，1–10000）
     * @param string $clientType 原始 X-Client-Type，可能为空串
     * @return array{order_no: string, payment_id: int, payment_data: array{trade_type: string, data: array<string, mixed>}}
     * @throws BusinessException 限流或待支付单过多 429；环境不支持、缺 openid、渠道不可用、下单失败 400
     */
    public function recharge(int $userId, string $amount, string $channel, string $clientType, string $clientIp): array
    {
        $this->assertWithinRateLimit($userId);

        $tradeType = self::MATRIX[$clientType][$channel] ?? throw new BusinessException(lang('payment.client_not_supported'));

        $openid = null;
        if ($tradeType === TradeType::JSAPI) {
            $openid = $this->openid($userId, $clientType);
        }

        if (!$this->gateways->isEnabled($channel)) {
            throw new BusinessException(lang('payment.unavailable'));
        }

        $this->assertPendingOrdersWithinLimit($userId);

        return $this->paymentService->createOrder(
            $userId,
            PaymentOrderRepository::BIZ_RECHARGE,
            $clientType,
            $channel,
            $tradeType,
            // 订单标题既落库又送给渠道（用户在支付页看到），固定中文，不随请求语言变化
            lang('payment.recharge_subject', [], 'zh_CN'),
            Money::toCents($amount),
            $openid,
            $clientIp,
            $channel === Channel::WECHAT ? $this->wechatAppId($clientType) : null,
        );
    }

    private function openid(int $userId, string $clientType): string
    {
        $user = $this->userRepository->find($userId);
        $openid = trim((string) ($user[self::OPENID_COLUMNS[$clientType]] ?? ''));
        if ($openid === '') {
            throw new BusinessException(lang('payment.wechat_auth_required'));
        }

        return $openid;
    }

    /** 解析不到任何 appid 时返回 null：驱动用 WechatPayConfig 的 appid，此时 PaymentManager 本就会因凭据不全拒绝 */
    private function wechatAppId(string $clientType): ?string
    {
        $key = self::WECHAT_APP_ID_KEYS[$clientType] ?? null;
        $appId = $key !== null ? trim((string) $this->config->getConfigValue($key, '')) : '';
        if ($appId === '') {
            $appId = trim((string) $this->config->getConfigValue('pay_wechat_app_id', ''));
        }

        return $appId === '' ? null : $appId;
    }

    /**
     * 未过期的待支付单达到上限即拒绝。先数后插不加锁：并发下单至多多出限流窗口内的几张，
     * 目的是挡住无限堆积，不需要精确。已过期未关的单不计，免得关单任务滞后时误伤正常用户。
     */
    private function assertPendingOrdersWithinLimit(int $userId): void
    {
        $limit = max(1, (int) config('payment.max_pending_orders', 5));
        if ($this->orders->countUnexpiredPendingForUser($userId, new \DateTimeImmutable()) >= $limit) {
            throw new BusinessException(lang('payment.too_many_pending'), 429);
        }
    }

    private function assertWithinRateLimit(int $userId): void
    {
        $limit = max(1, (int) config('payment.recharge_per_minute', 10));
        $count = (int) Redis::eval(self::INCR_WITH_TTL, 1, "payment_rate:recharge:{$userId}", self::RATE_WINDOW);
        if ($count > $limit) {
            throw new BusinessException(lang('payment.rate_limited'), 429);
        }
    }
}
