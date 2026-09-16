<?php

declare(strict_types=1);

namespace core\payment\driver;

use core\payment\config\WechatPayConfig;
use core\payment\dto\CreateOrderRequest;
use core\payment\dto\CreateOrderResult;
use core\payment\dto\NotifyAck;
use core\payment\dto\NotifyRequest;
use core\payment\dto\NotifyResult;
use core\payment\dto\RefundRequest;
use core\payment\dto\RefundResult;
use core\payment\dto\TradeQueryResult;
use core\payment\exception\GatewayException;
use core\payment\exception\GatewayResultUnknownException;
use core\payment\exception\NotifyVerificationException;
use core\payment\exception\PaymentConfigException;
use core\payment\PaymentGatewayInterface;
use core\payment\TradeType;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\ResponseInterface;
use WeChatPay\Builder;
use WeChatPay\BuilderChainable;
use WeChatPay\ClientDecoratorInterface;
use WeChatPay\Crypto\AesGcm;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Formatter;

/**
 * 微信支付 APIv3 驱动，封装官方 `wechatpay/wechatpay`（请求签名、应答验签由 SDK 负责）。
 *
 * 每次 PaymentManager::gateway() 都 new 一个实例，实例不跨请求复用；实例属性 $client 只是本实例内的惰性缓存。
 *
 * SDK 使用上的三个坑（均读源码核实，见计划 Task 4 异议）：
 *   1. Builder 构造强制 certs 非空 → 惰性建 client()，证书模式缓存为空时先下载平台证书；
 *   2. 链式段名会把大写字母改写（R2026… → r2026…）→ 带单号的路径一律写 URI 模板占位，单号放 options；
 *   3. verifier 挂在 http_errors 外层 → 4xx 应答不验签。4xx 只在 TLS 被攻破时可伪造，按「明确失败」处理。
 *
 * 平台证书缓存 {certCacheDir}/{mchId}/{SERIAL}.pem 按商户号隔离；下载限频用同目录 .refreshed_at 的 mtime
 * （不用静态属性，也不让 core 驱动依赖 Redis）。公钥模式下永不下载证书。
 * 证书轮换：应答验签因「序列号不在当前映射里」失败时，在限频允许下重下一次证书并丢弃已建客户端，
 * 本次请求仍按结果不确定抛出（不重试），下一次调用用新映射重建客户端。
 *
 * 缓存文件 I/O 一律静默（`@` + 返回值判断）：webman 把 Warning 转成 ErrorException，
 * 不静默的话多 worker 竞争删除、目录不可写等情形会以错误的异常类型逃出分类表。
 *
 * 异常消息只含渠道错误码、配置项与路径，不含私钥、APIv3 key、证书正文。
 */
final class WechatPayDriver implements PaymentGatewayInterface
{
    private const CURRENCY = 'CNY';

    private const REFUND_REASON_MAX_BYTES = 80;

    private const REFRESH_MARKER = '.refreshed_at';

    private const TIMEZONE = 'Asia/Shanghai';

    /** 回调时间戳允许的最大偏差（秒），与 SDK 对应答的 MAXIMUM_CLOCK_OFFSET 一致 */
    private const NOTIFY_CLOCK_SKEW = 300;

    private readonly \OpenSSLAsymmetricKey $merchantKey;

    private readonly ?\OpenSSLAsymmetricKey $publicKey;

    private ?BuilderChainable $client = null;

    /** @var list<string> 当前客户端验签映射里的序列号（证书轮换判定用） */
    private array $clientSerials = [];

    public function __construct(
        private readonly WechatPayConfig $config,
        private readonly ?HandlerStack $handler = null,
    ) {
        // 商户号会拼进证书缓存目录：只允许字母数字下划线横线，挡住 ../ 之类的值
        if (preg_match('/^[0-9A-Za-z_-]{1,32}$/', $config->mchId) !== 1) {
            throw new PaymentConfigException('微信支付商户号格式不正确');
        }
        $this->merchantKey = $this->loadMerchantKey();
        $this->publicKey = $config->usesPublicKey() ? $this->loadWechatPublicKey() : null;
    }

    public function create(CreateOrderRequest $request): CreateOrderResult
    {
        $endpoint = match ($request->tradeType) {
            TradeType::NATIVE => 'native',
            TradeType::H5     => 'h5',
            TradeType::APP    => 'app',
            TradeType::JSAPI  => 'jsapi',
            default           => throw new GatewayException("微信支付不支持的交易类型：{$request->tradeType}"),
        };

        $appId = $request->appId !== null && $request->appId !== '' ? $request->appId : $this->config->appId;
        $body = [
            'appid'        => $appId,
            'mchid'        => $this->config->mchId,
            'description'  => $request->subject,
            'out_trade_no' => $request->orderNo,
            'time_expire'  => $request->expiresAt->setTimezone(new \DateTimeZone(self::TIMEZONE))->format(\DATE_RFC3339),
            'notify_url'   => $request->notifyUrl,
            'amount'       => ['total' => $request->amountCents, 'currency' => self::CURRENCY],
        ];
        if ($request->tradeType === TradeType::JSAPI) {
            if ($request->openid === null || $request->openid === '') {
                throw new GatewayException('微信支付 JSAPI 下单缺少 openid');
            }
            $body['payer'] = ['openid' => $request->openid];
        }
        if ($request->tradeType === TradeType::H5) {
            if ($request->clientIp === null || $request->clientIp === '') {
                throw new GatewayException('微信支付 H5 下单缺少客户端 IP');
            }
            $body['scene_info'] = ['payer_client_ip' => $request->clientIp, 'h5_info' => ['type' => 'Wap']];
        }

        $result = $this->call('post', 'v3/pay/transactions/' . $endpoint, ['json' => $body]);
        if ($result['status'] >= 400) {
            throw new GatewayException('微信支付下单被拒绝：' . self::errorCode($result['body']));
        }

        $data = $result['body'];

        return match ($request->tradeType) {
            TradeType::NATIVE => new CreateOrderResult(TradeType::NATIVE, ['code_url' => self::requireString($data, 'code_url')]),
            TradeType::H5     => new CreateOrderResult(TradeType::H5, ['h5_url' => self::requireString($data, 'h5_url')]),
            TradeType::JSAPI  => new CreateOrderResult(TradeType::JSAPI, $this->jsapiParams($appId, self::requireString($data, 'prepay_id'))),
            default           => new CreateOrderResult(TradeType::APP, $this->appParams($appId, self::requireString($data, 'prepay_id'))),
        };
    }

    public function query(string $orderNo): TradeQueryResult
    {
        $result = $this->call('get', 'v3/pay/transactions/out-trade-no/{out_trade_no}', [
            'out_trade_no' => $orderNo,
            'query'        => ['mchid' => $this->config->mchId],
        ]);
        if ($result['status'] >= 400) {
            if (($result['body']['code'] ?? null) === 'ORDER_NOT_EXIST') {
                return new TradeQueryResult(TradeQueryResult::NOT_FOUND, raw: $result['body']);
            }
            throw new GatewayException('微信支付查单被拒绝：' . self::errorCode($result['body']));
        }

        $data = $result['body'];
        $tradeState = (string) ($data['trade_state'] ?? '');
        $state = match ($tradeState) {
            'SUCCESS', 'REFUND'                  => TradeQueryResult::PAID,
            'NOTPAY', 'USERPAYING', 'PAYERROR'   => TradeQueryResult::PENDING,
            'CLOSED', 'REVOKED'                  => TradeQueryResult::CLOSED,
            default                              => throw new GatewayResultUnknownException("微信支付查单返回未知状态：{$tradeState}"),
        };
        if ($state !== TradeQueryResult::PAID) {
            return new TradeQueryResult($state, raw: $data);
        }

        $total = $data['amount']['total'] ?? null;
        if (!is_int($total)) {
            throw new GatewayResultUnknownException('微信支付查单应答缺少 amount.total');
        }

        return new TradeQueryResult($state, self::requireString($data, 'transaction_id'), $total, $data);
    }

    public function close(string $orderNo): void
    {
        $result = $this->call('post', 'v3/pay/transactions/out-trade-no/{out_trade_no}/close', [
            'out_trade_no' => $orderNo,
            'json'         => ['mchid' => $this->config->mchId],
        ]);
        if ($result['status'] < 400) {
            return;
        }
        // 订单不存在（用户从未拉起支付）与已关闭都算关单成功；真实错误码以联调为准（计划 Task 4 异议 6）
        if (in_array($result['body']['code'] ?? null, ['ORDER_NOT_EXIST', 'ORDER_CLOSED'], true)) {
            return;
        }

        throw new GatewayException('微信支付关单被拒绝：' . self::errorCode($result['body']));
    }

    public function refund(RefundRequest $request): RefundResult
    {
        $body = [
            'out_trade_no'  => $request->orderNo,
            'out_refund_no' => $request->refundNo,
            'amount'        => ['refund' => $request->refundCents, 'total' => $request->totalCents, 'currency' => self::CURRENCY],
        ];
        if ($request->reason !== '') {
            // 微信限 80 字节；mb_strcut 按字节截且不截断多字节字符
            $body['reason'] = mb_strcut($request->reason, 0, self::REFUND_REASON_MAX_BYTES, 'UTF-8');
        }

        try {
            $result = $this->call('post', 'v3/refund/domestic/refunds', ['json' => $body]);
        } catch (GatewayException $e) {
            // 平台证书不可用：请求没有发出，可以确定「没退」
            return new RefundResult(RefundResult::FAILED, null, $e->getMessage());
        }
        if ($result['status'] >= 400) {
            return new RefundResult(RefundResult::FAILED, null, self::errorCode($result['body']));
        }

        return self::mapRefund($result['body']);
    }

    public function queryRefund(string $orderNo, string $refundNo): RefundResult
    {
        try {
            $result = $this->call('get', 'v3/refund/domestic/refunds/{out_refund_no}', ['out_refund_no' => $refundNo]);
        } catch (GatewayException $e) {
            throw new GatewayResultUnknownException('微信支付退款查询未发出：' . $e->getMessage(), 0, $e);
        }
        if ($result['status'] >= 400) {
            if (($result['body']['code'] ?? null) === 'RESOURCE_NOT_EXISTS') {
                return new RefundResult(RefundResult::NOT_FOUND);
            }
            // 查询被拒绝不能说明退款失败：交给下一轮对账
            throw new GatewayResultUnknownException('微信支付退款查询被拒绝：' . self::errorCode($result['body']));
        }

        return self::mapRefund($result['body']);
    }

    /**
     * 严格验证微信支付回调（spec §5.3）。任何一步不通过都抛 NotifyVerificationException，让网关重试。
     *
     * 只信任已验签的原始 body：$request->form（GET 与表单参数合并的结果）一律不读——否则攻击者可以带一段
     * 合法签名的 body，再用 query 参数替换 resource（SaaS 的缺陷）。
     */
    public function verifyNotify(NotifyRequest $request): NotifyResult
    {
        $timestamp = self::header($request, 'wechatpay-timestamp');
        $nonce = self::header($request, 'wechatpay-nonce');
        $signature = self::header($request, 'wechatpay-signature');
        $serial = self::header($request, 'wechatpay-serial');
        if ($timestamp === '' || $nonce === '' || $signature === '' || $serial === '') {
            throw new NotifyVerificationException('微信支付回调缺少签名头');
        }
        if (preg_match('/^\d{1,12}$/', $timestamp) !== 1 || abs(time() - (int) $timestamp) > self::NOTIFY_CLOCK_SKEW) {
            throw new NotifyVerificationException('微信支付回调时间戳超出允许范围');
        }

        $key = $this->notifyKey($serial);
        try {
            $verified = Rsa::verify(Formatter::response($timestamp, $nonce, $request->rawBody), $signature, $key);
        } catch (\Throwable) {
            $verified = false;
        }
        if (!$verified) {
            throw new NotifyVerificationException('微信支付回调验签失败');
        }

        $envelope = json_decode($request->rawBody, true);
        $resource = is_array($envelope) ? ($envelope['resource'] ?? null) : null;
        if (!is_array($resource) || ($resource['algorithm'] ?? null) !== 'AEAD_AES_256_GCM') {
            throw new NotifyVerificationException('微信支付回调报文结构不正确');
        }
        $ciphertext = is_string($resource['ciphertext'] ?? null) ? $resource['ciphertext'] : '';
        $resourceNonce = is_string($resource['nonce'] ?? null) ? $resource['nonce'] : '';
        $aad = is_string($resource['associated_data'] ?? null) ? $resource['associated_data'] : '';
        // nonce 为空时 openssl_decrypt 会发 Warning，先挡掉
        if ($ciphertext === '' || $resourceNonce === '') {
            throw new NotifyVerificationException('微信支付回调报文结构不正确');
        }
        try {
            $data = json_decode(AesGcm::decrypt($ciphertext, $this->config->apiV3Key, $resourceNonce, $aad), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw new NotifyVerificationException('微信支付回调解密失败');
        }
        if (!is_array($data)) {
            throw new NotifyVerificationException('微信支付回调解密结果不是对象');
        }

        if (!is_string($data['mchid'] ?? null) || $data['mchid'] !== $this->config->mchId) {
            throw new NotifyVerificationException('微信支付回调商户号不符');
        }
        $orderNo = is_string($data['out_trade_no'] ?? null) ? $data['out_trade_no'] : '';
        // 非支付成功事件（例如退款事件，资源里没有 appid）：已验签，应答成功、不处理
        if (($envelope['event_type'] ?? null) !== 'TRANSACTION.SUCCESS') {
            return new NotifyResult(false, $orderNo, raw: $data);
        }
        // 各端 appid 不同（M6a spec §7）：不与配置比较，只要求存在，交给 PaymentService 按订单 app_id 核对
        if (!is_string($data['appid'] ?? null) || $data['appid'] === '') {
            throw new NotifyVerificationException('微信支付回调缺少 appid');
        }
        if (($data['trade_state'] ?? null) !== 'SUCCESS') {
            return new NotifyResult(false, $orderNo, raw: $data);
        }

        $tradeNo = $data['transaction_id'] ?? null;
        $total = $data['amount']['total'] ?? null;
        if ($orderNo === '' || !is_string($tradeNo) || $tradeNo === '' || !is_int($total)) {
            throw new NotifyVerificationException('微信支付回调缺少订单号、交易号或金额');
        }

        return new NotifyResult(true, $orderNo, $tradeNo, $total, $data, $data['appid']);
    }

    public function notifyAck(bool $success): NotifyAck
    {
        return $success
            ? new NotifyAck(200, 'application/json', '{"code":"SUCCESS","message":"成功"}')
            : new NotifyAck(500, 'application/json', '{"code":"FAIL","message":"失败"}');
    }

    /**
     * 按回调头的序列号取验签公钥。公钥模式只认 publicKeyId、永不下载；证书模式缓存未命中且限频允许时
     * 重新下载一次（平台证书轮换），仍未命中即拒绝。
     *
     * downloadCerts() 只返回本次下载到的证书：回调序列号要么在其中，要么就是未知序列号，无需与旧缓存合并。
     *
     * @throws NotifyVerificationException
     */
    private function notifyKey(string $serial): \OpenSSLAsymmetricKey
    {
        if ($this->publicKey !== null) {
            if ($serial !== $this->config->publicKeyId) {
                throw new NotifyVerificationException('微信支付回调公钥 ID 不符');
            }

            return $this->publicKey;
        }

        $key = self::keyForSerial($this->loadCachedCerts(), $serial);
        if ($key === null && $this->refreshAllowed()) {
            try {
                $key = self::keyForSerial($this->downloadCerts(), $serial);
                // 缓存已更新：丢弃本实例可能已建的客户端，下次调用用新映射重建
                $this->client = null;
                $this->clientSerials = [];
            } catch (GatewayException) {
                $key = null;
            }
        }
        if ($key === null) {
            throw new NotifyVerificationException('微信支付回调证书序列号未知');
        }

        return $key;
    }

    /** @param array<string, \OpenSSLAsymmetricKey> $certs */
    private static function keyForSerial(array $certs, string $serial): ?\OpenSSLAsymmetricKey
    {
        foreach ($certs as $known => $key) {
            if (self::sameSerial((string) $known, $serial)) {
                return $key;
            }
        }

        return null;
    }

    private static function header(NotifyRequest $request, string $name): string
    {
        $value = $request->headers[$name] ?? '';

        return is_string($value) ? trim($value) : '';
    }

    // ------------------------------------------------------------------ HTTP

    /**
     * 发一次请求。2xx 与 4xx 返回状态码与解码后的应答体；其余一律视为结果不确定。
     *
     * @param 'get'|'post'         $method
     * @param array<string, mixed> $options Guzzle 选项；URI 模板占位（如 out_trade_no）也放这里
     * @return array{status: int, body: array<string, mixed>}
     * @throws GatewayException              证书模式下平台证书不可用（请求没有发出）
     * @throws GatewayResultUnknownException 连接或读超时、5xx、应答验签失败
     */
    private function call(string $method, string $uri, array $options = []): array
    {
        $client = $this->client();

        try {
            $chain = $client->chain($uri);
            $response = $method === 'get' ? $chain->get($options) : $chain->post($options);
        } catch (ClientException $e) {
            return ['status' => $e->getResponse()->getStatusCode(), 'body' => self::decode($e->getResponse())];
        } catch (\Throwable $e) {
            $this->refreshOnUnknownSerial($e);

            throw new GatewayResultUnknownException('微信支付请求结果不确定：' . $e::class, 0, $e);
        }

        return ['status' => $response->getStatusCode(), 'body' => self::decode($response)];
    }

    /**
     * 证书模式下，应答带回的 Wechatpay-Serial 不在当前验签映射里（平台证书已轮换）：限频允许时重下证书，
     * 并丢弃已建客户端，让下一次调用用新映射重建。尽力而为，下载失败不改变本次「结果不确定」的结论。
     */
    private function refreshOnUnknownSerial(\Throwable $e): void
    {
        if ($this->publicKey !== null || !$e instanceof RequestException) {
            return;
        }
        $serial = $e->getResponse()?->getHeaderLine('Wechatpay-Serial') ?? '';
        if ($serial === '' || in_array($serial, $this->clientSerials, true) || !$this->refreshAllowed()) {
            return;
        }

        try {
            $this->downloadCerts();
        } catch (\Throwable) {
            return;
        }
        $this->client = null;
        $this->clientSerials = [];
    }

    /** @throws GatewayException */
    private function client(): BuilderChainable
    {
        if ($this->client === null) {
            $certs = $this->verificationKeys();
            $this->client = Builder::factory($this->httpOptions([
                'mchid'      => $this->config->mchId,
                'serial'     => $this->config->merchantSerialNo,
                'privateKey' => $this->merchantKey,
                'certs'      => $certs,
            ]));
            $this->clientSerials = array_map('strval', array_keys($certs));
        }

        return $this->client;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function httpOptions(array $config): array
    {
        // Guzzle 默认 0 = 无限等待，常驻 worker 下一次挂死就永久占住一个进程
        $config['connect_timeout'] = $this->config->connectTimeout;
        $config['timeout'] = $this->config->timeout;
        if ($this->handler !== null) {
            $config['handler'] = $this->handler;
        }

        return $config;
    }

    /**
     * SDK 验应答签名用的「序列号 → 公钥」映射。
     *
     * @return array<string, \OpenSSLAsymmetricKey>
     * @throws GatewayException
     */
    private function verificationKeys(): array
    {
        if ($this->publicKey !== null) {
            return [(string) $this->config->publicKeyId => $this->publicKey];
        }

        $certs = $this->loadCachedCerts();
        if ($certs !== []) {
            return $certs;
        }
        if (!$this->refreshAllowed()) {
            throw new GatewayException('微信支付平台证书不可用（最近一次下载失败，稍后自动重试）');
        }

        return $this->downloadCerts();
    }

    // ------------------------------------------------------------------ 密钥与证书

    private function loadMerchantKey(): \OpenSSLAsymmetricKey
    {
        $configured = $this->config->privateKeyPath;
        $path = str_starts_with($configured, '/') ? $configured : base_path($configured);
        if (!is_file($path) || !is_readable($path)) {
            throw new PaymentConfigException("微信支付商户私钥文件不可读：{$path}");
        }

        $content = @file_get_contents($path);
        if ($content === false) {
            throw new PaymentConfigException("微信支付商户私钥文件不可读：{$path}");
        }

        try {
            $key = Rsa::from($content, Rsa::KEY_TYPE_PRIVATE);
        } catch (\Throwable) {
            $key = null;
        }
        if (!$key instanceof \OpenSSLAsymmetricKey) {
            throw new PaymentConfigException("微信支付商户私钥无效：{$path}");
        }

        return $key;
    }

    private function loadWechatPublicKey(): \OpenSSLAsymmetricKey
    {
        $pem = self::normalizePublicKey((string) $this->config->publicKey);
        $key = $pem === null ? null : self::publicKeyFrom($pem);
        if ($key === null) {
            throw new PaymentConfigException('微信支付公钥无效（pay_wechat_public_key）');
        }

        return $key;
    }

    /** 接受带或不带 PEM 头尾行的公钥正文；不是合法 base64 返回 null */
    private static function normalizePublicKey(string $raw): ?string
    {
        $base64 = (string) preg_replace('/-----[A-Z ]+-----|\s+/', '', $raw);
        if ($base64 === '' || base64_decode($base64, true) === false) {
            return null;
        }

        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split($base64, 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    /** 公钥 PEM 或 X.509 证书 PEM → 公钥；无效返回 null */
    private static function publicKeyFrom(string $pem): ?\OpenSSLAsymmetricKey
    {
        try {
            $key = Rsa::from($pem, Rsa::KEY_TYPE_PUBLIC);
        } catch (\Throwable) {
            return null;
        }

        return $key instanceof \OpenSSLAsymmetricKey ? $key : null;
    }

    private function certDir(): string
    {
        return rtrim($this->config->certCacheDir, '/') . '/' . $this->config->mchId;
    }

    /**
     * 读本商户的证书缓存：键取文件名（即下发时的序列号，与应答头 Wechatpay-Serial 一致），
     * 过期的顺手删掉，与商户证书同序列号的跳过（SDK 禁止二者同键）。
     *
     * @return array<string, \OpenSSLAsymmetricKey>
     */
    private function loadCachedCerts(): array
    {
        $certs = [];
        foreach (glob($this->certDir() . '/*.pem') ?: [] as $file) {
            $serial = basename($file, '.pem');
            $pem = @file_get_contents($file);
            if ($pem === false) {
                continue;
            }
            $info = openssl_x509_parse($pem);
            if ($info === false || !self::sameSerial((string) ($info['serialNumberHex'] ?? ''), $serial)) {
                continue;
            }
            if ((int) ($info['validTo_time_t'] ?? 0) <= time()) {
                // 另一个 worker 可能已先删掉：失败即忽略
                @unlink($file);
                continue;
            }
            if (self::sameSerial($serial, $this->config->merchantSerialNo)) {
                continue;
            }
            $key = self::publicKeyFrom($pem);
            if ($key !== null) {
                $certs[$serial] = $key;
            }
        }

        return $certs;
    }

    private function refreshAllowed(): bool
    {
        $marker = $this->certDir() . '/' . self::REFRESH_MARKER;
        clearstatcache(true, $marker);
        $mtime = @filemtime($marker);

        return $mtime === false || time() - $mtime >= $this->config->certRefreshInterval;
    }

    /**
     * 下载平台证书并写缓存，返回下载到的有效证书。
     *
     * 自举：SDK 的 verifier 要用「应答里下发的证书」验这份应答自己的签名。做法是以占位 certs 的**引用**建
     * Builder，在 verifier 内侧（after 'verifier'，越靠后越在内层）挂 injector，先把解密出的证书写进同一个
     * 数组。引用元素经数组按值传参后仍指向同一变量（SaaS 已验证），所以 verifier 看得到 injector 写入的证书。
     *
     * 先打限频标记再发请求：失败也计入限频。
     *
     * @return array<string, \OpenSSLAsymmetricKey>
     * @throws GatewayException
     */
    private function downloadCerts(): array
    {
        $dir = $this->certDir();
        // mkdir 失败后再判一次 is_dir：两个 worker 同时创建时，后到者失败但目录已存在
        if (!is_dir($dir) && !@mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw new GatewayException('无法创建微信支付平台证书缓存目录');
        }
        // 限频标记写不进去就不发请求：否则每次调用都会重下证书
        if (!@touch($dir . '/' . self::REFRESH_MARKER)) {
            throw new GatewayException('无法写入微信支付平台证书限频标记');
        }

        $apiV3Key = $this->config->apiV3Key;
        $downloaded = ['__bootstrap__' => null];
        $instance = Builder::factory($this->httpOptions([
            'mchid'      => $this->config->mchId,
            'serial'     => $this->config->merchantSerialNo,
            'privateKey' => $this->merchantKey,
            'certs'      => &$downloaded,
        ]));

        $stack = $instance->getDriver()->select(ClientDecoratorInterface::JSON_BASED)->getConfig('handler');
        if (!$stack instanceof HandlerStack) {
            throw new GatewayException('微信支付 SDK 未提供可挂载的 HandlerStack');
        }
        $stack->after('verifier', Middleware::mapResponse(
            static function (ResponseInterface $response) use ($apiV3Key, &$downloaded): ResponseInterface {
                $payload = json_decode((string) $response->getBody(), true);
                foreach (is_array($payload) && is_array($payload['data'] ?? null) ? $payload['data'] : [] as $row) {
                    $serial = strtoupper((string) ($row['serial_no'] ?? ''));
                    $encrypted = $row['encrypt_certificate'] ?? null;
                    if (preg_match('/^[0-9A-F]{1,64}$/', $serial) !== 1 || !is_array($encrypted)) {
                        continue;
                    }
                    $ciphertext = (string) ($encrypted['ciphertext'] ?? '');
                    $nonce = (string) ($encrypted['nonce'] ?? '');
                    // nonce 为空时 openssl_decrypt 会发 Warning，先挡掉
                    if ($ciphertext === '' || $nonce === '') {
                        continue;
                    }
                    try {
                        $downloaded[$serial] = AesGcm::decrypt($ciphertext, $apiV3Key, $nonce, (string) ($encrypted['associated_data'] ?? ''));
                    } catch (\Throwable) {
                        continue;
                    }
                }

                return $response;
            }
        ), 'injector');

        try {
            $instance->chain('v3/certificates')->get();
        } catch (\Throwable $e) {
            throw new GatewayException('下载微信支付平台证书失败：' . $e::class, 0, $e);
        }

        $certs = [];
        foreach ($downloaded as $serial => $pem) {
            if (!is_string($pem) || self::sameSerial((string) $serial, $this->config->merchantSerialNo)) {
                continue;
            }
            $info = openssl_x509_parse($pem);
            // 证书真实序列号必须与下发声称的一致，且未过期
            if ($info === false
                || !self::sameSerial((string) ($info['serialNumberHex'] ?? ''), (string) $serial)
                || (int) ($info['validTo_time_t'] ?? 0) <= time()) {
                continue;
            }
            $key = self::publicKeyFrom($pem);
            if ($key === null) {
                continue;
            }
            $file = $dir . '/' . $serial . '.pem';
            $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
            if (@file_put_contents($tmp, $pem) === false || !@rename($tmp, $file)) {
                @unlink($tmp);
                continue;
            }
            $certs[(string) $serial] = $key;
        }

        if ($certs === []) {
            throw new GatewayException('未获取到有效的微信支付平台证书');
        }

        return $certs;
    }

    /** 序列号比较：忽略大小写与前导 0（openssl 的 serialNumberHex 不保留前导 0） */
    private static function sameSerial(string $a, string $b): bool
    {
        return $a !== '' && $b !== '' && ltrim(strtoupper($a), '0') === ltrim(strtoupper($b), '0');
    }

    // ------------------------------------------------------------------ 调起参数与映射

    /** @return array{appId: string, timeStamp: string, nonceStr: string, package: string, signType: string, paySign: string} */
    private function jsapiParams(string $appId, string $prepayId): array
    {
        $timeStamp = (string) Formatter::timestamp();
        $nonceStr = Formatter::nonce();
        $package = 'prepay_id=' . $prepayId;

        return [
            'appId'     => $appId,
            'timeStamp' => $timeStamp,
            'nonceStr'  => $nonceStr,
            'package'   => $package,
            'signType'  => 'RSA',
            'paySign'   => Rsa::sign(Formatter::joinedByLineFeed($appId, $timeStamp, $nonceStr, $package), $this->merchantKey),
        ];
    }

    /** @return array{appid: string, partnerid: string, prepayid: string, package: string, noncestr: string, timestamp: string, sign: string} */
    private function appParams(string $appId, string $prepayId): array
    {
        $timestamp = (string) Formatter::timestamp();
        $noncestr = Formatter::nonce();

        return [
            'appid'     => $appId,
            'partnerid' => $this->config->mchId,
            'prepayid'  => $prepayId,
            'package'   => 'Sign=WXPay',
            'noncestr'  => $noncestr,
            'timestamp' => $timestamp,
            'sign'      => Rsa::sign(Formatter::joinedByLineFeed($appId, $timestamp, $noncestr, $prepayId), $this->merchantKey),
        ];
    }

    /** @param array<string, mixed> $data */
    private static function mapRefund(array $data): RefundResult
    {
        $channelRefundNo = isset($data['refund_id']) ? (string) $data['refund_id'] : null;
        $status = (string) ($data['status'] ?? '');

        return match ($status) {
            'SUCCESS'    => new RefundResult(RefundResult::SUCCESS, $channelRefundNo),
            'PROCESSING' => new RefundResult(RefundResult::PROCESSING, $channelRefundNo),
            // 退款异常：需要商户在微信支付后台人工处理；对系统而言仍是「处理中」
            'ABNORMAL'   => new RefundResult(RefundResult::PROCESSING, $channelRefundNo, 'ABNORMAL'),
            'CLOSED'     => new RefundResult(RefundResult::FAILED, $channelRefundNo, 'CLOSED'),
            default      => throw new GatewayResultUnknownException("微信支付退款返回未知状态：{$status}"),
        };
    }

    /** @return array<string, mixed> */
    private static function decode(ResponseInterface $response): array
    {
        $decoded = json_decode((string) $response->getBody(), true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string, mixed> $data */
    private static function requireString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value) || $value === '') {
            // 2xx 却缺关键字段：渠道侧可能已受理，按「结果不确定」处理
            throw new GatewayResultUnknownException("微信支付应答缺少字段：{$key}");
        }

        return $value;
    }

    /** @param array<string, mixed> $body */
    private static function errorCode(array $body): string
    {
        return trim((string) ($body['code'] ?? 'UNKNOWN') . ' ' . (string) ($body['message'] ?? ''));
    }
}
