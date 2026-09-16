<?php

declare(strict_types=1);

namespace tests\RedLine;

use PHPUnit\Framework\Attributes\DataProvider;
use support\Db;
use tests\Support\ApiTestCase;
use tests\Support\Payment\PaymentKeys;

/**
 * 红线（M5b spec §5.3、§12「微信缺序列号时跳过验签」「回调不比对商户」）：支付回调是公开端点，唯一的
 * 身份凭证就是签名。未签名、错签、签名对不上报文、序列号未知、时间戳超窗、商户 appid 不符——任何一种
 * 都不能让订单变 paid，也不能写出一条入账流水。
 *
 * 1.x 的微信驱动在 Wechatpay-Serial 缺失或不在缓存时**静默跳过** RSA 验签，只剩 AES-GCM 解密兜底；
 * 这条红线把「验签失败即拒绝」钉死在 HTTP 层，经真实路由、真实驱动、真实 markPaid 走完整条链。
 *
 * 每组失败用例都配一个同样拼法、但签名正确的正向对照（test_valid_*），证明失败确实来自签名，而不是
 * 夹具本身就发不通。
 *
 * 微信走公钥模式（pay_wechat_public_key_id + pay_wechat_public_key）：未知序列号直接拒绝、从不下载
 * 平台证书，整条测试不触网。
 */
final class Test23_PaymentNotifyStrictVerificationTest extends ApiTestCase
{
    private const WX_APP_ID = 'wxrl23000000000001';
    private const WX_MCH_ID = '1900000023';
    private const WX_API_V3_KEY = 'rl23rl23rl23rl23rl23rl23rl23rl23';
    private const WX_MERCHANT_SERIAL = 'RL23MERCHANTSERIAL0001';
    private const WX_PUBLIC_KEY_ID = 'PUB_KEY_ID_0123456789RL23';
    private const ALI_APP_ID = '2021000000000023';

    private string $tempDir = '';

    /** @var array{private:string, public:string} 微信支付平台（公钥模式）的密钥对：私钥签回调，公钥进配置 */
    private array $wechatPlatform = [];

    /** @var array{private:string, public:string} 支付宝的密钥对：私钥签回调，公钥进配置 */
    private array $alipayPlatform = [];

    /** @var array{private:string, public:string} 攻击者的密钥对 */
    private array $attacker = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = PaymentKeys::tempDir();
        $merchant = PaymentKeys::rsaPair();
        $this->wechatPlatform = PaymentKeys::rsaPair();
        $this->alipayPlatform = PaymentKeys::rsaPair();
        $this->attacker = PaymentKeys::rsaPair();
        $appKey = PaymentKeys::rsaPair();

        $privateKeyPath = $this->tempDir . '/apiclient_key.pem';
        file_put_contents($privateKeyPath, $merchant['private']);

        // 回调验签不看 enabled 开关（spec §5.8），这里刻意不开，同时钉住这一点
        $this->setConfig('pay_wechat_app_id', self::WX_APP_ID);
        $this->setConfig('pay_wechat_mch_id', self::WX_MCH_ID);
        $this->setConfig('pay_wechat_api_v3_key', self::WX_API_V3_KEY);
        $this->setConfig('pay_wechat_serial_no', self::WX_MERCHANT_SERIAL);
        $this->setConfig('pay_wechat_private_key_path', $privateKeyPath);
        $this->setConfig('pay_wechat_public_key_id', self::WX_PUBLIC_KEY_ID);
        $this->setConfig('pay_wechat_public_key', $this->wechatPlatform['public']);

        $this->setConfig('pay_alipay_app_id', self::ALI_APP_ID);
        $this->setConfig('pay_alipay_private_key', $appKey['private']);
        $this->setConfig('pay_alipay_public_key', $this->alipayPlatform['public']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        PaymentKeys::removeDir($this->tempDir);
    }

    /** @return array<string, array{string}> */
    public static function wechatForgeries(): array
    {
        return [
            '没有任何签名头'           => ['no_headers'],
            '攻击者私钥签名、序列号正确' => ['attacker_signature'],
            '签名正确但序列号未知'       => ['unknown_serial'],
            '签名后篡改报文'            => ['tampered_body'],
            '签名正确但时间戳超窗'       => ['stale_timestamp'],
        ];
    }

    #[DataProvider('wechatForgeries')]
    public function test_forged_wechat_notify_never_marks_paid(string $case): void
    {
        [$userId, $orderNo] = $this->pendingOrder('wechat', 'native');
        $body = $this->wechatNotifyBody($orderNo, 1000);

        $headers = match ($case) {
            'no_headers'         => [],
            'attacker_signature' => $this->wechatHeaders($body, $this->attacker['private'], self::WX_PUBLIC_KEY_ID),
            'unknown_serial'     => $this->wechatHeaders($body, $this->wechatPlatform['private'], 'PUB_KEY_ID_ATTACKER0000'),
            'tampered_body'      => $this->wechatHeaders($body, $this->wechatPlatform['private'], self::WX_PUBLIC_KEY_ID),
            'stale_timestamp'    => $this->wechatHeaders($body, $this->wechatPlatform['private'], self::WX_PUBLIC_KEY_ID, time() - 301),
        };
        if ($case === 'tampered_body') {
            // 签的是原报文，发出去的报文多了一个字段：字节变了，签名必须失效
            $body = substr($body, 0, -1) . ',"rl23":"tampered"}';
        }

        $response = $this->postRaw('/api/payment/notify/wechat', $body, ['Content-Type' => 'application/json'] + $headers);

        $this->assertSame(500, $response->status(), "{$case}：伪造的微信回调必须得到 HTTP 500，让微信重试而不是当成已处理");
        $this->assertSame('FAIL', $response->json()['code'] ?? null, "{$case}：应答体必须是 FAIL");
        $this->assertOrderUntouched($userId, $orderNo, $case);
    }

    public function test_valid_wechat_notify_marks_paid_once(): void
    {
        [$userId, $orderNo] = $this->pendingOrder('wechat', 'native');
        $body = $this->wechatNotifyBody($orderNo, 1000);
        $headers = $this->wechatHeaders($body, $this->wechatPlatform['private'], self::WX_PUBLIC_KEY_ID);

        $response = $this->postRaw('/api/payment/notify/wechat', $body, ['Content-Type' => 'application/json'] + $headers);

        $this->assertSame(200, $response->status(), '正向对照：签名正确的回调必须被接受，否则上面的失败用例证明不了什么。响应：' . $response->body());
        $this->assertSame('SUCCESS', $response->json()['code'] ?? null);
        $this->assertSame('paid', Db::table('payment_orders')->where('order_no', $orderNo)->value('status'));
        $this->trackBalanceLogs($orderNo);
        $this->assertSame(1, Db::table('balance_logs')->where('source', 'payment:' . $orderNo)->count());
        $this->assertSame('10.00', (string) Db::table('users')->where('id', $userId)->value('balance'));
    }

    /** @return array<string, array{string}> */
    public static function alipayForgeries(): array
    {
        return [
            '没有 sign'               => ['no_sign'],
            '攻击者私钥签名'           => ['attacker_signature'],
            '签名后篡改金额'           => ['tampered_amount'],
            '签名正确但 app_id 不是本商户' => ['foreign_app_id'],
        ];
    }

    #[DataProvider('alipayForgeries')]
    public function test_forged_alipay_notify_never_marks_paid(string $case): void
    {
        [$userId, $orderNo] = $this->pendingOrder('alipay', 'page');
        $params = $this->alipayNotifyParams($orderNo, '10.00');

        switch ($case) {
            case 'no_sign':
                break;
            case 'attacker_signature':
                $params['sign'] = $this->alipaySign($params, $this->attacker['private']);
                break;
            case 'tampered_amount':
                $params['sign'] = $this->alipaySign($params, $this->alipayPlatform['private']);
                $params['total_amount'] = '0.01';
                break;
            case 'foreign_app_id':
                $params['app_id'] = '2021000000009999';
                $params['sign'] = $this->alipaySign($params, $this->alipayPlatform['private']);
                break;
        }

        $response = $this->postRaw('/api/payment/notify/alipay', http_build_query($params), ['Content-Type' => 'application/x-www-form-urlencoded; charset=utf-8']);

        $this->assertSame(200, $response->status(), "{$case}：支付宝失败应答是 HTTP 200");
        $this->assertSame('fail', $response->body(), "{$case}：伪造的支付宝回调必须应答 fail");
        $this->assertOrderUntouched($userId, $orderNo, $case);
    }

    public function test_valid_alipay_notify_marks_paid_once(): void
    {
        [$userId, $orderNo] = $this->pendingOrder('alipay', 'page');
        $params = $this->alipayNotifyParams($orderNo, '10.00');
        $params['sign'] = $this->alipaySign($params, $this->alipayPlatform['private']);

        $response = $this->postRaw('/api/payment/notify/alipay', http_build_query($params), ['Content-Type' => 'application/x-www-form-urlencoded; charset=utf-8']);

        $this->assertSame('success', $response->body(), '正向对照：签名正确的支付宝回调必须被接受');
        $this->assertSame('paid', Db::table('payment_orders')->where('order_no', $orderNo)->value('status'));
        $this->trackBalanceLogs($orderNo);
        $this->assertSame(1, Db::table('balance_logs')->where('source', 'payment:' . $orderNo)->count());
        $this->assertSame('10.00', (string) Db::table('users')->where('id', $userId)->value('balance'));
    }

    /** @return array{int, string} [用户 id, 订单号] */
    private function pendingOrder(string $channel, string $tradeType): array
    {
        $user = $this->actingAsUser(['balance' => '0.00']);
        $orderNo = 'R' . date('YmdHis') . sprintf('%08d', random_int(0, 99_999_999));
        $now = date('Y-m-d H:i:s');
        $this->track('payment_orders', (int) Db::table('payment_orders')->insertGetId([
            'user_id'        => $user->id,
            'biz_type'       => 'recharge',
            'client_type'    => 'pc',
            'order_no'       => $orderNo,
            'channel'        => $channel,
            'trade_type'     => $tradeType,
            'subject'        => '余额充值',
            'amount_cents'   => 1000,
            'refunded_cents' => 0,
            'status'         => 'pending',
            'expires_at'     => date('Y-m-d H:i:s', time() + 1800),
            'created_at'     => $now,
            'updated_at'     => $now,
        ]));

        return [$user->id, $orderNo];
    }

    private function assertOrderUntouched(int $userId, string $orderNo, string $case): void
    {
        $order = Db::table('payment_orders')->where('order_no', $orderNo)->first();
        $this->assertSame('pending', $order->status, "{$case}：订单必须仍是 pending");
        $this->assertNull($order->paid_at, "{$case}：不能写 paid_at");
        $this->assertNull($order->trade_no, "{$case}：不能写渠道交易号");
        $this->assertSame(0, Db::table('balance_logs')->where('user_id', $userId)->count(), "{$case}：不能写出任何余额流水");
        $this->assertSame('0.00', (string) Db::table('users')->where('id', $userId)->value('balance'), "{$case}：余额必须不变");
    }

    private function trackBalanceLogs(string $orderNo): void
    {
        foreach (Db::table('balance_logs')->where('source', 'payment:' . $orderNo)->pluck('id') as $id) {
            $this->track('balance_logs', (int) $id);
        }
    }

    /** 微信支付成功通知报文：resource 用 APIv3 key 做 AEAD_AES_256_GCM 加密（密文 = 密文体 . 16 字节 tag，再 base64） */
    private function wechatNotifyBody(string $orderNo, int $totalCents): string
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        $transaction = (string) json_encode([
            'appid'            => self::WX_APP_ID,
            'mchid'            => self::WX_MCH_ID,
            'out_trade_no'     => $orderNo,
            'transaction_id'   => '42000' . sprintf('%023d', random_int(0, PHP_INT_MAX)),
            'trade_type'       => 'NATIVE',
            'trade_state'      => 'SUCCESS',
            'trade_state_desc' => '支付成功',
            'bank_type'        => 'OTHERS',
            'success_time'     => date(DATE_RFC3339),
            'payer'            => ['openid' => 'o-rl23-payer'],
            'amount'           => ['total' => $totalCents, 'payer_total' => $totalCents, 'currency' => 'CNY', 'payer_currency' => 'CNY'],
        ], $flags);
        $nonce = substr(bin2hex(random_bytes(6)), 0, 12);
        $associatedData = 'transaction';
        $tag = '';
        $cipher = (string) openssl_encrypt($transaction, 'aes-256-gcm', self::WX_API_V3_KEY, OPENSSL_RAW_DATA, $nonce, $tag, $associatedData, 16);

        return (string) json_encode([
            'id'            => 'EV-rl23-' . bin2hex(random_bytes(6)),
            'create_time'   => date(DATE_RFC3339),
            'resource_type' => 'encrypt-resource',
            'event_type'    => 'TRANSACTION.SUCCESS',
            'summary'       => '支付成功',
            'resource'      => [
                'original_type'   => 'transaction',
                'algorithm'       => 'AEAD_AES_256_GCM',
                'ciphertext'      => base64_encode($cipher . $tag),
                'associated_data' => $associatedData,
                'nonce'           => $nonce,
            ],
        ], $flags);
    }

    /**
     * 微信 APIv3 回调签名：SHA256withRSA("时间戳\n随机串\n报文\n")，base64。
     *
     * @return array<string, string>
     */
    private function wechatHeaders(string $body, string $signerPrivatePem, string $serial, ?int $timestamp = null): array
    {
        $ts = (string) ($timestamp ?? time());
        $nonce = bin2hex(random_bytes(16));
        openssl_sign("{$ts}\n{$nonce}\n{$body}\n", $signature, $signerPrivatePem, OPENSSL_ALGO_SHA256);

        return [
            'Wechatpay-Timestamp' => $ts,
            'Wechatpay-Nonce'     => $nonce,
            'Wechatpay-Signature' => base64_encode((string) $signature),
            'Wechatpay-Serial'    => $serial,
        ];
    }

    /**
     * 支付宝异步通知参数（全部非空，避免「空值是否参与签名」的实现差异影响本测试）。
     *
     * @return array<string, string>
     */
    private function alipayNotifyParams(string $orderNo, string $totalAmount): array
    {
        return [
            'app_id'       => self::ALI_APP_ID,
            'charset'      => 'utf-8',
            'notify_id'    => 'rl23' . bin2hex(random_bytes(8)),
            'notify_time'  => date('Y-m-d H:i:s'),
            'notify_type'  => 'trade_status_sync',
            'out_trade_no' => $orderNo,
            'sign_type'    => 'RSA2',
            'total_amount' => $totalAmount,
            'trade_no'     => '2026' . sprintf('%024d', random_int(0, PHP_INT_MAX)),
            'trade_status' => 'TRADE_SUCCESS',
            'version'      => '1.0',
        ];
    }

    /** 支付宝 RSA2：去掉 sign、sign_type，按键升序拼 k=v&k=v（值不做 URL 编码），SHA256withRSA，base64 */
    private function alipaySign(array $params, string $privatePem): string
    {
        unset($params['sign'], $params['sign_type']);
        ksort($params);
        $pairs = [];
        foreach ($params as $key => $value) {
            $pairs[] = "{$key}={$value}";
        }
        openssl_sign(implode('&', $pairs), $signature, $privatePem, OPENSSL_ALGO_SHA256);

        return base64_encode((string) $signature);
    }
}
