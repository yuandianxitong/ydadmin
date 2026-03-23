<?php
declare(strict_types=1);

namespace core\payment\driver;

use core\payment\PaymentInterface;
use core\exception\BusinessException;
use WeChatPay\Builder;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Crypto\AesGcm;
use WeChatPay\ClientDecoratorInterface;
use WeChatPay\Formatter;
use GuzzleHttp\Middleware;
use Psr\Http\Message\ResponseInterface;

class WechatPayDriver implements PaymentInterface
{
    protected array $config;
    protected $instance;

    public function __construct(array $config)
    {
        if (empty($config['mch_id']) || empty($config['api_v3_key']) || empty($config['private_key_path'])) {
            throw new BusinessException(lang('business.wechat_pay_config_incomplete'));
        }

        $this->config = $config;
        $this->initSdk();
    }

    protected function initSdk(): void
    {
        $privateKeyPath = $this->config['private_key_path'];

        // 支持相对路径和绝对路径
        if (!str_starts_with($privateKeyPath, '/')) {
            $privateKeyPath = app()->getRootPath() . $privateKeyPath;
        }

        if (!file_exists($privateKeyPath)) {
            throw new BusinessException(lang('business.wechat_pay_key_not_found') . ': ' . $privateKeyPath);
        }

        $merchantPrivateKeyInstance = Rsa::from('file://' . $privateKeyPath, Rsa::KEY_TYPE_PRIVATE);
        $merchantCertificateSerial = $this->config['serial_no'] ?? '';

        // 加载平台证书：优先从缓存读取，否则自动下载
        $certs = $this->loadPlatformCerts($merchantPrivateKeyInstance, $merchantCertificateSerial);

        $this->instance = Builder::factory([
            'mchid'      => $this->config['mch_id'],
            'serial'     => $merchantCertificateSerial,
            'privateKey' => $merchantPrivateKeyInstance,
            'certs'      => $certs,
        ]);
    }

    /**
     * 加载微信平台证书（自动下载并缓存）
     */
    protected function loadPlatformCerts($merchantPrivateKey, string $merchantSerial): array
    {
        $cacheDir = app()->getRuntimePath() . 'cert' . DIRECTORY_SEPARATOR;
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        // 尝试从缓存加载
        $certs = $this->loadCachedCerts($cacheDir, $merchantSerial);
        if (!empty($certs)) {
            return $certs;
        }

        // 自动下载平台证书
        return $this->downloadPlatformCerts($merchantPrivateKey, $merchantSerial, $cacheDir);
    }

    /**
     * 从缓存目录加载平台证书
     */
    protected function loadCachedCerts(string $cacheDir, string $merchantSerial): array
    {
        $certs = [];
        $pattern = $cacheDir . 'wechatpay_*.pem';
        $files = glob($pattern) ?: [];

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $cert = openssl_x509_read($content);
            if (!$cert) {
                continue;
            }
            $info = openssl_x509_parse($cert);
            // 跳过已过期的证书
            if (isset($info['validTo_time_t']) && $info['validTo_time_t'] < time()) {
                unlink($file);
                continue;
            }
            $serial = strtoupper($info['serialNumberHex']);
            // 排除商户证书
            if ($serial === strtoupper($merchantSerial)) {
                continue;
            }
            $certs[$serial] = Rsa::from($content, Rsa::KEY_TYPE_PUBLIC);
        }

        return $certs;
    }

    /**
     * 从微信支付API自动下载平台证书
     * 参考 SDK 自带的 CertificateDownloader 实现
     */
    protected function downloadPlatformCerts($merchantPrivateKey, string $merchantSerial, string $cacheDir): array
    {
        $apiV3Key = $this->config['api_v3_key'];

        // 使用与 CertificateDownloader 相同的技巧：先用占位 certs 创建实例
        $certs = ['any' => null];

        $instance = Builder::factory([
            'mchid'      => $this->config['mch_id'],
            'serial'     => $merchantSerial,
            'privateKey' => $merchantPrivateKey,
            'certs'      => &$certs,
        ]);

        // 注入中间件：在 verifier 之前解密并填充真实证书
        /** @var \GuzzleHttp\HandlerStack $stack */
        $stack = $instance->getDriver()->select(ClientDecoratorInterface::JSON_BASED)->getConfig('handler');
        $stack->after('verifier', Middleware::mapResponse(
            static function (ResponseInterface $response) use ($apiV3Key, &$certs): ResponseInterface {
                $body = (string) $response->getBody();
                $json = json_decode($body);
                $data = is_object($json) && isset($json->data) && is_array($json->data) ? $json->data : [];
                foreach ($data as $row) {
                    $cert = $row->encrypt_certificate;
                    $certs[$row->serial_no] = AesGcm::decrypt($cert->ciphertext, $apiV3Key, $cert->nonce, $cert->associated_data);
                }
                return $response;
            }
        ), 'injector');

        try {
            $instance->chain('v3/certificates')->get();
        } catch (\Exception $e) {
            throw new BusinessException('自动下载微信平台证书失败: ' . $e->getMessage());
        }

        // 移除占位项，保存真实证书到缓存
        unset($certs['any']);
        $result = [];
        foreach ($certs as $serial => $certContent) {
            if (!is_string($certContent)) {
                continue;
            }
            // 缓存到文件
            $filePath = $cacheDir . 'wechatpay_' . $serial . '.pem';
            file_put_contents($filePath, $certContent);

            $result[$serial] = Rsa::from($certContent, Rsa::KEY_TYPE_PUBLIC);
        }

        if (empty($result)) {
            throw new BusinessException('未获取到有效的微信平台证书');
        }

        return $result;
    }

    public function create(array $order): array
    {
        try {
            $tradeType = $order['trade_type'] ?? 'native';
            $appId = $order['appid'] ?? $this->config['app_id'] ?? '';

            $params = [
                'json' => [
                    'appid'        => $appId,
                    'mchid'        => $this->config['mch_id'],
                    'description'  => $order['subject'] ?? '',
                    'out_trade_no' => $order['out_trade_no'],
                    'notify_url'   => $this->resolveNotifyUrl($order['notify_url'] ?? $this->config['notify_url'] ?? ''),
                    'amount'       => [
                        'total'    => (int)bcmul((string)$order['total_amount'], '100', 0),
                        'currency' => 'CNY',
                    ],
                ],
            ];

            // 根据交易类型选择不同 API
            $endpoint = match ($tradeType) {
                'native' => 'v3/pay/transactions/native',
                'jsapi'  => 'v3/pay/transactions/jsapi',
                'h5'     => 'v3/pay/transactions/h5',
                'app'    => 'v3/pay/transactions/app',
                default  => throw new BusinessException("不支持的微信支付交易类型: {$tradeType}"),
            };

            // JSAPI 需要 payer 信息
            if ($tradeType === 'jsapi' && !empty($order['openid'])) {
                $params['json']['payer'] = ['openid' => $order['openid']];
            }

            // H5 需要场景信息
            if ($tradeType === 'h5') {
                $params['json']['scene_info'] = [
                    'payer_client_ip' => $order['client_ip'] ?? '127.0.0.1',
                    'h5_info'         => ['type' => 'Wap'],
                ];
            }

            $resp = $this->instance->chain($endpoint)->post($params);
            $result = json_decode($resp->getBody()->getContents(), true);

            // 根据交易类型组装前端所需的支付参数
            $payData = match ($tradeType) {
                'jsapi'  => $this->buildJsapiParams($appId, $result['prepay_id'] ?? ''),
                'app'    => $this->buildAppParams($appId, $result['prepay_id'] ?? ''),
                'native' => ['code_url' => $result['code_url'] ?? ''],
                'h5'     => ['h5_url' => $result['h5_url'] ?? ''],
                default  => $result,
            };

            return [
                'trade_type' => $tradeType,
                'data'       => $payData,
            ];
        } catch (BusinessException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new BusinessException(lang('business.wechat_pay_create_order_failed') . ': ' . $e->getMessage());
        }
    }

    /**
     * 组装 JSAPI（小程序/公众号）调起支付的签名参数
     */
    protected function buildJsapiParams(string $appId, string $prepayId): array
    {
        $merchantPrivateKey = $this->getMerchantPrivateKey();

        $params = [
            'appId'     => $appId,
            'timeStamp' => (string)Formatter::timestamp(),
            'nonceStr'  => Formatter::nonce(),
            'package'   => 'prepay_id=' . $prepayId,
            'signType'  => 'RSA',
        ];
        $params['paySign'] = Rsa::sign(
            Formatter::joinedByLineFeed($params['appId'], $params['timeStamp'], $params['nonceStr'], $params['package']),
            $merchantPrivateKey
        );
        return $params;
    }

    /**
     * 组装 APP 调起支付的签名参数
     */
    protected function buildAppParams(string $appId, string $prepayId): array
    {
        $merchantPrivateKey = $this->getMerchantPrivateKey();

        $params = [
            'appid'     => $appId,
            'partnerid' => $this->config['mch_id'],
            'prepayid'  => $prepayId,
            'package'   => 'Sign=WXPay',
            'noncestr'  => Formatter::nonce(),
            'timestamp' => (string)Formatter::timestamp(),
        ];
        $params['sign'] = Rsa::sign(
            Formatter::joinedByLineFeed($params['appid'], $params['timestamp'], $params['noncestr'], $params['prepayid']),
            $merchantPrivateKey
        );
        return $params;
    }

    /**
     * 获取商户私钥实例
     */
    protected function getMerchantPrivateKey()
    {
        $privateKeyPath = $this->config['private_key_path'];
        if (!str_starts_with($privateKeyPath, '/')) {
            $privateKeyPath = app()->getRootPath() . $privateKeyPath;
        }
        return Rsa::from('file://' . $privateKeyPath, Rsa::KEY_TYPE_PRIVATE);
    }

    /**
     * 解析回调通知 URL，相对路径自动补全当前域名
     */
    protected function resolveNotifyUrl(string $url): string
    {
        if (empty($url)) {
            return '';
        }
        // 已经是完整 URL
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }
        // 相对路径，从当前请求补全域名
        $request = app()->request;
        $scheme = $request->scheme();
        $host = $request->host();
        return $scheme . '://' . $host . '/' . ltrim($url, '/');
    }

    public function query(string $outTradeNo): array
    {
        try {
            // 使用 URI 模板避免 SDK normalize() 将大写订单号转为 kebab-case
            $resp = $this->instance
                ->chain('v3/pay/transactions/out-trade-no/{out_trade_no}')
                ->get([
                    'out_trade_no' => $outTradeNo,
                    'query'        => ['mchid' => $this->config['mch_id']],
                ]);

            $result = json_decode($resp->getBody()->getContents(), true);

            return [
                'out_trade_no' => $result['out_trade_no'] ?? $outTradeNo,
                'trade_no'     => $result['transaction_id'] ?? '',
                'total_amount' => isset($result['amount']['total']) ? bcdiv((string)$result['amount']['total'], '100', 2) : '0',
                'status'       => $this->mapStatus($result['trade_state'] ?? ''),
                'raw'          => $result,
            ];
        } catch (\Exception $e) {
            // ORDER_NOT_EXIST 对于新创建的订单是正常的，返回 pending 而非报错
            if (str_contains($e->getMessage(), 'ORDER_NOT_EXIST')) {
                return [
                    'out_trade_no' => $outTradeNo,
                    'trade_no'     => '',
                    'total_amount' => '0',
                    'status'       => 'pending',
                    'raw'          => [],
                ];
            }
            throw new BusinessException(lang('business.wechat_pay_query_order_failed') . ': ' . $e->getMessage());
        }
    }

    public function refund(array $refund): array
    {
        try {
            $params = [
                'json' => [
                    'out_trade_no'  => $refund['out_trade_no'],
                    'out_refund_no' => $refund['out_refund_no'] ?? ('R' . $refund['out_trade_no']),
                    'reason'        => $refund['reason'] ?? '退款',
                    'amount'        => [
                        'refund'   => (int)bcmul((string)$refund['refund_amount'], '100', 0),
                        'total'    => (int)bcmul((string)$refund['total_amount'], '100', 0),
                        'currency' => 'CNY',
                    ],
                ],
            ];

            $resp = $this->instance->chain('v3/refund/domestic/refunds')->post($params);
            $result = json_decode($resp->getBody()->getContents(), true);

            return [
                'out_trade_no'  => $result['out_trade_no'] ?? '',
                'trade_no'      => $result['transaction_id'] ?? '',
                'refund_amount' => isset($result['amount']['refund']) ? bcdiv((string)$result['amount']['refund'], '100', 2) : '0',
                'status'        => ($result['status'] ?? '') === 'SUCCESS' ? 'success' : 'processing',
                'raw'           => $result,
            ];
        } catch (\Exception $e) {
            throw new BusinessException(lang('business.wechat_pay_refund_failed') . ': ' . $e->getMessage());
        }
    }

    public function verifyNotify(array $params): array
    {
        try {
            // 1. 验证请求签名（如果提供了签名头信息）
            if (isset($params['_headers'])) {
                $this->verifySignature($params['_headers'], $params['_body'] ?? '');
            }

            // 2. 解密回调数据
            $resource = $params['resource'] ?? [];
            $ciphertext = $resource['ciphertext'] ?? '';
            $nonce = $resource['nonce'] ?? '';
            $associatedData = $resource['associated_data'] ?? '';

            if (empty($ciphertext)) {
                throw new BusinessException(lang('business.callback_data_empty'));
            }

            $decrypted = AesGcm::decrypt($ciphertext, $this->config['api_v3_key'], $nonce, $associatedData);
            $data = json_decode($decrypted, true);

            if (!is_array($data) || empty($data['out_trade_no'])) {
                throw new BusinessException(lang('business.callback_data_decrypt_error'));
            }

            return [
                'out_trade_no' => $data['out_trade_no'],
                'trade_no'     => $data['transaction_id'] ?? '',
                'total_amount' => isset($data['amount']['total']) ? bcdiv((string)$data['amount']['total'], '100', 2) : '0',
                'status'       => $this->mapStatus($data['trade_state'] ?? ''),
                'raw'          => $data,
            ];
        } catch (BusinessException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new BusinessException(lang('business.wechat_pay_callback_verify_failed') . ': ' . $e->getMessage());
        }
    }

    /**
     * 验证微信回调请求签名
     */
    protected function verifySignature(array $headers, string $body): void
    {
        $timestamp = $headers['Wechatpay-Timestamp'] ?? '';
        $nonce = $headers['Wechatpay-Nonce'] ?? '';
        $signature = $headers['Wechatpay-Signature'] ?? '';
        $serial = $headers['Wechatpay-Serial'] ?? '';

        if (empty($timestamp) || empty($nonce) || empty($signature)) {
            throw new BusinessException(lang('business.wechat_callback_missing_signature'));
        }

        // 检查时间戳是否在合理范围内（5分钟）
        if (abs(time() - (int)$timestamp) > 300) {
            throw new BusinessException(lang('business.wechat_callback_timestamp_expired'));
        }

        // 构造验签串
        $message = $timestamp . "\n" . $nonce . "\n" . $body . "\n";

        // 从缓存的平台证书验签
        $cacheDir = app()->getRuntimePath() . 'cert' . DIRECTORY_SEPARATOR;
        $merchantSerial = $this->config['serial_no'] ?? '';
        $certs = $this->loadCachedCerts($cacheDir, $merchantSerial);

        if (!empty($serial) && isset($certs[$serial])) {
            $verified = Rsa::verify($message, $signature, $certs[$serial]);
            if (!$verified) {
                throw new BusinessException(lang('business.wechat_callback_sign_failed'));
            }
        }
    }

    public function successResponse(): string
    {
        return json_encode(['code' => 'SUCCESS', 'message' => '成功']);
    }

    public function getDriver(): string
    {
        return 'wechat';
    }

    protected function mapStatus(string $tradeState): string
    {
        return match ($tradeState) {
            'SUCCESS'    => 'paid',
            'CLOSED'     => 'closed',
            'NOTPAY'     => 'pending',
            'USERPAYING' => 'pending',
            'REFUND'     => 'refunded',
            default      => 'unknown',
        };
    }
}
