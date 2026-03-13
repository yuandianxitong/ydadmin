<?php
declare(strict_types=1);

namespace core\payment\driver;

use core\payment\PaymentInterface;
use core\exception\BusinessException;
use WeChatPay\Builder;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Crypto\AesGcm;

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

        $this->instance = Builder::factory([
            'mchid'      => $this->config['mch_id'],
            'serial'     => $merchantCertificateSerial,
            'privateKey' => $merchantPrivateKeyInstance,
            'certs'      => [], // 平台证书，正式环境需配置
        ]);
    }

    public function create(array $order): array
    {
        try {
            $tradeType = $order['trade_type'] ?? 'native';
            $appId = $this->config['app_id'] ?? '';

            $params = [
                'json' => [
                    'appid'        => $appId,
                    'mchid'        => $this->config['mch_id'],
                    'description'  => $order['subject'] ?? '',
                    'out_trade_no' => $order['out_trade_no'],
                    'notify_url'   => $order['notify_url'] ?? $this->config['notify_url'] ?? '',
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

            return [
                'trade_type' => $tradeType,
                'data'       => $result,
            ];
        } catch (BusinessException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new BusinessException(lang('business.wechat_pay_create_order_failed') . ': ' . $e->getMessage());
        }
    }

    public function query(string $outTradeNo): array
    {
        try {
            $resp = $this->instance
                ->chain("v3/pay/transactions/out-trade-no/{$outTradeNo}")
                ->get(['query' => ['mchid' => $this->config['mch_id']]]);

            $result = json_decode($resp->getBody()->getContents(), true);

            return [
                'out_trade_no' => $result['out_trade_no'] ?? $outTradeNo,
                'trade_no'     => $result['transaction_id'] ?? '',
                'total_amount' => isset($result['amount']['total']) ? bcdiv((string)$result['amount']['total'], '100', 2) : '0',
                'status'       => $this->mapStatus($result['trade_state'] ?? ''),
                'raw'          => $result,
            ];
        } catch (\Exception $e) {
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

        if (empty($timestamp) || empty($nonce) || empty($signature)) {
            throw new BusinessException(lang('business.wechat_callback_missing_signature'));
        }

        // 检查时间戳是否在合理范围内（5分钟）
        if (abs(time() - (int)$timestamp) > 300) {
            throw new BusinessException(lang('business.wechat_callback_timestamp_expired'));
        }

        // 构造验签串
        $message = $timestamp . "\n" . $nonce . "\n" . $body . "\n";

        // 使用平台证书验签（如果配置了证书路径）
        $certPath = $this->config['cert_path'] ?? '';
        if ($certPath) {
            if (!str_starts_with($certPath, '/')) {
                $certPath = app()->getRootPath() . $certPath;
            }

            if (file_exists($certPath)) {
                $publicKey = Rsa::from('file://' . $certPath, Rsa::KEY_TYPE_PUBLIC);
                $verified = Rsa::verify($message, $signature, $publicKey);

                if (!$verified) {
                    throw new BusinessException(lang('business.wechat_callback_sign_failed'));
                }
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
