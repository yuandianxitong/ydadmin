<?php

declare(strict_types=1);

namespace core\payment\driver;

use core\payment\config\AlipayConfig;
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
use core\payment\Money;
use core\payment\PaymentGatewayInterface;
use core\payment\TradeType;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;

/**
 * 支付宝开放平台驱动（公钥模式、RSA2），手写而不用 alipaysdk/easysdk（spec §1.4）：
 * EasySDK 自 2022-11 未发版、靠进程级静态 Factory 持有凭据（常驻内存下会串）、超时写死 15 秒。
 *
 * - page / wap / app 三种下单只在本地签名生成表单或订单串，不发网络请求；
 * - 查单、关单、退款、退款查询是一次表单 POST，应答按「原文截取 + 顶层 sign」验签；
 * - 失败分类（计划设计决定 3–6）：连接/超时/非 200/验签不过/无签名 → 结果不确定；
 *   code 以 4 开头的业务错误（sub_code 以 ACQ.SYSTEM_ERROR 结尾的除外）→ 明确失败；其余 → 不确定。
 *
 * 实例不缓存、不持有请求态：PaymentManager 每次现读配置现 new（计划设计决定 2）。
 */
final class AlipayDriver implements PaymentGatewayInterface
{
    /** @var array<string, array{0: string, 1: string}> trade_type => [method, product_code] */
    private const PAY_METHODS = [
        TradeType::PAGE => ['alipay.trade.page.pay', 'FAST_INSTANT_TRADE_PAY'],
        TradeType::WAP  => ['alipay.trade.wap.pay', 'QUICK_WAP_WAY'],
        TradeType::APP  => ['alipay.trade.app.pay', 'QUICK_MSECURITY_PAY'],
    ];

    /** 支付宝的 timestamp、time_expire 都按北京时间解释 */
    private const TIMEZONE = 'Asia/Shanghai';

    private const TRADE_NOT_EXIST = 'ACQ.TRADE_NOT_EXIST';

    /** 文档要求「使用相同参数再次调用」，结果未知；按后缀匹配，兼容 aop.ACQ.SYSTEM_ERROR 等带前缀的形式 */
    private const SYSTEM_ERROR = 'ACQ.SYSTEM_ERROR';

    /** 网关层拒绝（业务未执行）的公共错误码：未签名时也按明确失败处理 */
    private const GATEWAY_REJECTION_CODES = ['40001', '40002', '40006'];

    private readonly \OpenSSLAsymmetricKey $privateKey;

    private readonly \OpenSSLAsymmetricKey $alipayPublicKey;

    private readonly ClientInterface $http;

    public function __construct(private readonly AlipayConfig $config, ?HandlerStack $handler = null)
    {
        $this->privateKey = self::loadPrivateKey($config->privateKey);
        $this->alipayPublicKey = self::loadPublicKey($config->alipayPublicKey);

        $options = [
            'connect_timeout' => $config->connectTimeout,
            'timeout'         => $config->timeout,
            'http_errors'     => false,
        ];
        if ($handler !== null) {
            $options['handler'] = $handler;
        }
        $this->http = new Client($options);
    }

    public function create(CreateOrderRequest $request): CreateOrderResult
    {
        if (!isset(self::PAY_METHODS[$request->tradeType])) {
            throw new GatewayException('支付宝不支持的交易类型：' . $request->tradeType);
        }
        [$method, $productCode] = self::PAY_METHODS[$request->tradeType];

        $params = $this->commonParams($method);
        $params['notify_url'] = $request->notifyUrl;
        $params['biz_content'] = self::encodeBiz([
            'out_trade_no' => $request->orderNo,
            'total_amount' => Money::toYuan($request->amountCents),
            'subject'      => $request->subject,
            'product_code' => $productCode,
            'time_expire'  => $request->expiresAt->setTimezone(new \DateTimeZone(self::TIMEZONE))->format('Y-m-d H:i:s'),
        ]);
        $params['sign'] = $this->sign($params);

        $body = $request->tradeType === TradeType::APP ? http_build_query($params) : $this->buildForm($params);

        return new CreateOrderResult($request->tradeType, ['body' => $body]);
    }

    public function query(string $orderNo): TradeQueryResult
    {
        $node = $this->execute('alipay.trade.query', ['out_trade_no' => $orderNo]);

        if (self::code($node) !== '10000') {
            if (self::subCode($node) === self::TRADE_NOT_EXIST) {
                return new TradeQueryResult(TradeQueryResult::NOT_FOUND, null, null, $node);
            }
            throw self::businessError('alipay.trade.query', $node);
        }

        $tradeNo = self::stringField($node, 'trade_no');
        $status = (string) ($node['trade_status'] ?? '');

        return match ($status) {
            'TRADE_SUCCESS', 'TRADE_FINISHED' => new TradeQueryResult(TradeQueryResult::PAID, $tradeNo, self::cents($node, 'alipay.trade.query'), $node),
            'WAIT_BUYER_PAY' => new TradeQueryResult(TradeQueryResult::PENDING, $tradeNo, null, $node),
            'TRADE_CLOSED'   => new TradeQueryResult(TradeQueryResult::CLOSED, $tradeNo, null, $node),
            default          => throw new GatewayResultUnknownException("支付宝查单返回未知交易状态：{$status}"),
        };
    }

    public function close(string $orderNo): void
    {
        $node = $this->execute('alipay.trade.close', ['out_trade_no' => $orderNo]);

        // 用户从未扫码时支付宝侧没有建单，关单报 TRADE_NOT_EXIST：对「这笔单不能再被支付」而言与关单成功等价
        if (self::code($node) === '10000' || self::subCode($node) === self::TRADE_NOT_EXIST) {
            return;
        }

        throw self::businessError('alipay.trade.close', $node);
    }

    public function refund(RefundRequest $request): RefundResult
    {
        try {
            $node = $this->execute('alipay.trade.refund', [
                'out_trade_no'   => $request->orderNo,
                'refund_amount'  => Money::toYuan($request->refundCents),
                'out_request_no' => $request->refundNo,
                'refund_reason'  => $request->reason,
            ]);
        } catch (GatewayException $e) {
            // 两种来源，都代表退款业务确定没有执行：
            // 1) 本地签名或编码失败，请求没有发出；
            // 2) 网关在进入业务前拒绝的未签名 error_response（code 40001/40002/40006，见 verifiedNode()）
            return new RefundResult(RefundResult::FAILED, null, $e->getMessage());
        }

        if (self::code($node) === '10000') {
            // fund_change=N：本次调用没有产生资金变动（可能处理中，也可能此前已退过），交给对账查询确认
            $status = ($node['fund_change'] ?? '') === 'Y' ? RefundResult::SUCCESS : RefundResult::PROCESSING;

            return new RefundResult($status, self::stringField($node, 'trade_no'));
        }

        $error = self::businessError('alipay.trade.refund', $node);
        if ($error instanceof GatewayException) {
            return new RefundResult(RefundResult::FAILED, null, $error->getMessage());
        }

        throw $error;
    }

    public function queryRefund(string $orderNo, string $refundNo): RefundResult
    {
        try {
            $node = $this->execute('alipay.trade.fastpay.refund.query', [
                'out_trade_no'   => $orderNo,
                'out_request_no' => $refundNo,
            ]);
        } catch (GatewayException $e) {
            throw new GatewayResultUnknownException($e->getMessage(), 0, $e);
        }

        if (self::code($node) !== '10000') {
            if (self::subCode($node) === self::TRADE_NOT_EXIST) {
                return new RefundResult(RefundResult::NOT_FOUND);
            }
            // 查询失败不等于退款失败：一律按不确定处理，绝不能据此冲正
            throw new GatewayResultUnknownException(self::businessError('alipay.trade.fastpay.refund.query', $node)->getMessage());
        }

        // 支付宝文档：返回了查询数据，且 refund_status 为空或为 REFUND_SUCCESS，即代表退款成功；查不到数据代表未退款。
        // 「查询数据」只认非空的 refund_amount：退款不存在时应答仍可能回显请求里的 out_request_no，
        // 若据此判成功，结算会让用户余额扣着而钱没有退出去。
        $status = (string) ($node['refund_status'] ?? '');
        if ($status === 'REFUND_SUCCESS') {
            return new RefundResult(RefundResult::SUCCESS, self::stringField($node, 'trade_no'));
        }
        if ($status !== '') {
            return new RefundResult(RefundResult::PROCESSING, self::stringField($node, 'trade_no'));
        }
        if (self::stringField($node, 'refund_amount') !== null) {
            return new RefundResult(RefundResult::SUCCESS, self::stringField($node, 'trade_no'));
        }

        return new RefundResult(RefundResult::NOT_FOUND);
    }

    public function verifyNotify(NotifyRequest $request): NotifyResult
    {
        $params = [];
        foreach ($request->form as $key => $value) {
            if (!is_string($value)) {
                throw new NotifyVerificationException('支付宝回调参数格式非法');
            }
            $params[(string) $key] = $value;
        }

        $sign = $params['sign'] ?? '';
        if ($sign === '') {
            throw new NotifyVerificationException('支付宝回调缺少签名');
        }
        if (isset($params['sign_type']) && $params['sign_type'] !== 'RSA2') {
            throw new NotifyVerificationException('支付宝回调签名类型不是 RSA2');
        }
        $signature = base64_decode($sign, true);
        if ($signature === false
            || openssl_verify(self::notifyContent($params), $signature, $this->alipayPublicKey, OPENSSL_ALGO_SHA256) !== 1) {
            throw new NotifyVerificationException('支付宝回调验签失败');
        }
        if (($params['app_id'] ?? '') !== $this->config->appId) {
            throw new NotifyVerificationException('支付宝回调 app_id 与配置不符');
        }

        $orderNo = $params['out_trade_no'] ?? '';
        if ($orderNo === '') {
            throw new NotifyVerificationException('支付宝回调缺少 out_trade_no');
        }

        unset($params['sign'], $params['sign_type']);
        if (!in_array($params['trade_status'] ?? '', ['TRADE_SUCCESS', 'TRADE_FINISHED'], true)) {
            return new NotifyResult(false, $orderNo, null, null, $params);
        }

        try {
            $paidCents = Money::toCents($params['total_amount'] ?? '');
        } catch (\InvalidArgumentException $e) {
            throw new NotifyVerificationException('支付宝回调金额非法', 0, $e);
        }
        $tradeNo = ($params['trade_no'] ?? '') !== '' ? $params['trade_no'] : null;

        return new NotifyResult(true, $orderNo, $tradeNo, $paidCents, $params);
    }

    public function notifyAck(bool $success): NotifyAck
    {
        return new NotifyAck(200, 'text/plain', $success ? 'success' : 'fail');
    }

    /**
     * 发一次开放平台调用并返回已验签的应答节点。
     *
     * @param array<string, string> $biz
     * @return array<string, mixed>
     */
    private function execute(string $method, array $biz): array
    {
        $params = $this->commonParams($method);
        $params['biz_content'] = self::encodeBiz($biz);
        $params['sign'] = $this->sign($params);

        try {
            $response = $this->http->request('POST', $this->config->gatewayUrl() . '?charset=utf-8', ['form_params' => $params]);
        } catch (GuzzleException $e) {
            throw new GatewayResultUnknownException("支付宝 {$method} 请求未得到应答：" . $e->getMessage(), 0, $e);
        }

        $status = $response->getStatusCode();
        if ($status !== 200) {
            throw new GatewayResultUnknownException("支付宝 {$method} 应答 HTTP {$status}");
        }

        return $this->verifiedNode((string) $response->getBody(), $method);
    }

    /** @return array<string, mixed> */
    private function verifiedNode(string $body, string $method): array
    {
        $raw = self::extractNode($body, str_replace('.', '_', $method) . '_response')
            ?? self::extractNode($body, 'error_response');
        if ($raw === null) {
            throw new GatewayResultUnknownException("支付宝 {$method} 应答缺少响应节点");
        }

        try {
            $document = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            $node = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new GatewayResultUnknownException("支付宝 {$method} 应答不是合法 JSON", 0, $e);
        }
        if (!is_array($document) || !is_array($node)) {
            throw new GatewayResultUnknownException("支付宝 {$method} 应答结构非法");
        }

        $sign = $document['sign'] ?? null;
        if (!is_string($sign) || $sign === '') {
            $code = self::code($node);
            $isErrorNode = self::extractNode($body, str_replace('.', '_', $method) . '_response') === null;
            if ($isErrorNode && in_array($code, self::GATEWAY_REJECTION_CODES, true)) {
                // 网关在进入业务之前就拒绝了请求（凭据、参数、权限），业务不可能已执行：明确失败。
                // 这类应答支付宝本就不签名；伪造它需要攻破 TLS，与微信 4xx 不验签同级（拼装裁定）。
                throw new GatewayException("支付宝 {$method} 被网关拒绝：{$code} " . self::subCode($node));
            }
            // 其余未签名应答真伪无法判断，按不确定处理（fail closed）
            throw new GatewayResultUnknownException("支付宝 {$method} 应答未签名：" . (string) ($node['sub_code'] ?? $node['code'] ?? ''));
        }
        $signature = base64_decode($sign, true);
        if ($signature === false || openssl_verify($raw, $signature, $this->alipayPublicKey, OPENSSL_ALGO_SHA256) !== 1) {
            throw new GatewayResultUnknownException("支付宝 {$method} 应答验签失败");
        }

        /** @var array<string, mixed> $node */
        return $node;
    }

    /**
     * 从原始 body 截出 "<nodeName>": 之后那个 JSON 对象的原文（验签必须用原文，不能重新编码）。
     * 按括号配对扫描并识别字符串与转义，不依赖 sign 字段的位置。节点名出现两次视为不确定。
     */
    private static function extractNode(string $body, string $nodeName): ?string
    {
        $needle = '"' . $nodeName . '"';
        $position = strpos($body, $needle);
        if ($position === false) {
            return null;
        }
        if ($position !== strrpos($body, $needle)) {
            throw new GatewayResultUnknownException("支付宝应答含重复的 {$nodeName} 节点");
        }

        $length = strlen($body);
        $i = $position + strlen($needle);
        while ($i < $length && ctype_space($body[$i])) {
            $i++;
        }
        if ($i >= $length || $body[$i] !== ':') {
            return null;
        }
        $i++;
        while ($i < $length && ctype_space($body[$i])) {
            $i++;
        }
        if ($i >= $length || $body[$i] !== '{') {
            return null;
        }

        $start = $i;
        $depth = 0;
        $inString = false;
        for (; $i < $length; $i++) {
            $char = $body[$i];
            if ($inString) {
                if ($char === '\\') {
                    $i++;
                } elseif ($char === '"') {
                    $inString = false;
                }
                continue;
            }
            if ($char === '"') {
                $inString = true;
            } elseif ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($body, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }

    /** @return array<string, string> */
    private function commonParams(string $method): array
    {
        return [
            'app_id'    => $this->config->appId,
            'method'    => $method,
            'format'    => 'JSON',
            'charset'   => 'utf-8',
            'sign_type' => 'RSA2',
            'timestamp' => (new \DateTimeImmutable('now', new \DateTimeZone(self::TIMEZONE)))->format('Y-m-d H:i:s'),
            'version'   => '1.0',
        ];
    }

    /** @param array<string, string> $params */
    private function sign(array $params): string
    {
        if (!openssl_sign(self::signContent($params), $signature, $this->privateKey, OPENSSL_ALGO_SHA256)) {
            throw new GatewayException('支付宝请求签名失败');
        }

        return base64_encode($signature);
    }

    /**
     * 请求签名原文：去掉 sign，丢弃 trim 后为空的值，按键升序拼 k=v&k=v（值不做 URL 编码）。
     * 与 alipaysdk/easysdk 2.2.3 `EasySDKKernel::getSignContent()`（checkEmpty 用 trim 判空）一致；
     * SDK 另跳过以「@」开头的值（旧版 curl 文件上传约定），本驱动发出的参数不会以「@」开头，不照搬。
     *
     * @param array<string, string> $params
     */
    private static function signContent(array $params): string
    {
        unset($params['sign']);
        ksort($params);
        $pairs = [];
        foreach ($params as $key => $value) {
            if (trim($value) !== '') {
                $pairs[] = $key . '=' . $value;
            }
        }

        return implode('&', $pairs);
    }

    /**
     * 回调验签原文：去掉 sign、sign_type，按键升序拼 k=v&…，空值保留（支付宝文档：其余参数皆参与验签）。
     * 与 alipaysdk/easysdk 2.2.3 `Kernel/Util/Signer::getSignContent()`（verifyNotify 所用，不判空）一致。
     * SDK 另会跳过以「@」开头的值；这里刻意不跳过：被跳过的字段不受签名保护，宁可让这种回调验签失败（fail closed，
     * 订单仍由查单兜底），也不接受未经签名的字段。
     *
     * @param array<string, string> $params
     */
    private static function notifyContent(array $params): string
    {
        unset($params['sign'], $params['sign_type']);
        ksort($params);
        $pairs = [];
        foreach ($params as $key => $value) {
            $pairs[] = $key . '=' . $value;
        }

        return implode('&', $pairs);
    }

    /** @param array<string, string> $params */
    private function buildForm(array $params): string
    {
        $html = '<form id="alipaysubmit" name="alipaysubmit" action="'
            . self::escape($this->config->gatewayUrl() . '?charset=utf-8') . '" method="POST">';
        foreach ($params as $key => $value) {
            $html .= '<input type="hidden" name="' . self::escape($key) . '" value="' . self::escape($value) . '"/>';
        }

        return $html . '<input type="submit" value="ok" style="display:none;"/></form><script>document.forms[0].submit();</script>';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** @param array<string, string> $biz */
    private static function encodeBiz(array $biz): string
    {
        $biz = array_filter($biz, static fn (string $value): bool => $value !== '');
        try {
            return json_encode($biz, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new GatewayException('支付宝业务参数无法编码', 0, $e);
        }
    }

    /** @param array<string, mixed> $node */
    private static function businessError(string $method, array $node): GatewayException|GatewayResultUnknownException
    {
        $code = self::code($node);
        $subCode = self::subCode($node);
        $message = trim("支付宝 {$method} 失败：{$code} {$subCode} " . (string) ($node['sub_msg'] ?? $node['msg'] ?? ''));

        return str_starts_with($code, '4') && !str_ends_with($subCode, self::SYSTEM_ERROR)
            ? new GatewayException($message)
            : new GatewayResultUnknownException($message);
    }

    /** @param array<string, mixed> $node */
    private static function code(array $node): string
    {
        return is_scalar($node['code'] ?? null) ? (string) $node['code'] : '';
    }

    /** @param array<string, mixed> $node */
    private static function subCode(array $node): string
    {
        return is_scalar($node['sub_code'] ?? null) ? (string) $node['sub_code'] : '';
    }

    /** @param array<string, mixed> $node */
    private static function stringField(array $node, string $key): ?string
    {
        $value = $node[$key] ?? null;

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    /** @param array<string, mixed> $node */
    private static function cents(array $node, string $method): int
    {
        try {
            return Money::toCents((string) self::stringField($node, 'total_amount'));
        } catch (\InvalidArgumentException $e) {
            throw new GatewayResultUnknownException("支付宝 {$method} 应答金额非法", 0, $e);
        }
    }

    private static function loadPrivateKey(string $raw): \OpenSSLAsymmetricKey
    {
        foreach (self::pemCandidates($raw, ['PRIVATE KEY', 'RSA PRIVATE KEY']) as $pem) {
            $key = openssl_pkey_get_private($pem);
            if ($key !== false) {
                return $key;
            }
        }

        throw new PaymentConfigException('支付宝应用私钥无效');
    }

    private static function loadPublicKey(string $raw): \OpenSSLAsymmetricKey
    {
        foreach (self::pemCandidates($raw, ['PUBLIC KEY']) as $pem) {
            $key = openssl_pkey_get_public($pem);
            if ($key !== false) {
                return $key;
            }
        }

        throw new PaymentConfigException('支付宝公钥无效');
    }

    /**
     * 支付宝开放平台下载的密钥常是不带头尾行的一整行 base64：补齐 64 列折行与头尾。
     * 私钥依次尝试 PKCS#8（PRIVATE KEY）与 PKCS#1（RSA PRIVATE KEY）。
     *
     * @param list<string> $labels
     * @return list<string>
     */
    private static function pemCandidates(string $raw, array $labels): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }
        if (str_contains($raw, '-----BEGIN ')) {
            return [$raw];
        }

        $body = chunk_split((string) preg_replace('/\s+/', '', $raw), 64, "\n");

        return array_map(
            static fn (string $label): string => "-----BEGIN {$label}-----\n{$body}-----END {$label}-----\n",
            $labels,
        );
    }
}
