<?php
declare(strict_types=1);

namespace core\payment\driver;

use core\payment\PaymentInterface;
use core\exception\BusinessException;
use Alipay\EasySDK\Kernel\Factory as AlipayFactory;
use Alipay\EasySDK\Kernel\Config as AlipayConfig;

class AlipayDriver implements PaymentInterface
{
    protected array $config;

    public function __construct(array $config)
    {
        if (empty($config['app_id']) || empty($config['private_key']) || empty($config['public_key'])) {
            throw new BusinessException(lang('business.alipay_config_incomplete'));
        }

        $this->config = $config;
        $this->initSdk();
    }

    protected function initSdk(): void
    {
        $options = new AlipayConfig();
        $options->protocol = 'https';
        $options->gatewayHost = 'openapi.alipay.com';
        $options->signType = 'RSA2';
        $options->appId = $this->config['app_id'];
        $options->merchantPrivateKey = $this->config['private_key'];
        $options->alipayPublicKey = $this->config['public_key'];
        $options->notifyUrl = $this->config['notify_url'] ?? '';

        AlipayFactory::setOptions($options);
    }

    public function create(array $order): array
    {
        try {
            $tradeType = $order['trade_type'] ?? 'page';

            $result = match ($tradeType) {
                'page' => AlipayFactory::payment()->page()->pay(
                    $order['subject'],
                    $order['out_trade_no'],
                    (string)$order['total_amount'],
                    $order['return_url'] ?? ''
                ),
                'wap' => AlipayFactory::payment()->wap()->pay(
                    $order['subject'],
                    $order['out_trade_no'],
                    (string)$order['total_amount'],
                    $order['quit_url'] ?? '',
                    $order['return_url'] ?? ''
                ),
                'app' => AlipayFactory::payment()->app()->pay(
                    $order['subject'],
                    $order['out_trade_no'],
                    (string)$order['total_amount']
                ),
                default => throw new BusinessException("不支持的支付宝交易类型: {$tradeType}"),
            };

            return [
                'trade_type' => $tradeType,
                'body'       => $result->body,
            ];
        } catch (BusinessException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new BusinessException(lang('business.alipay_create_order_failed') . ': ' . $e->getMessage());
        }
    }

    public function query(string $outTradeNo): array
    {
        try {
            $result = AlipayFactory::payment()->common()->query($outTradeNo);
            $data = json_decode($result->httpBody, true);
            $response = $data['alipay_trade_query_response'] ?? [];

            return [
                'out_trade_no' => $response['out_trade_no'] ?? $outTradeNo,
                'trade_no'     => $response['trade_no'] ?? '',
                'total_amount' => $response['total_amount'] ?? '0',
                'status'       => $this->mapStatus($response['trade_status'] ?? ''),
                'raw'          => $response,
            ];
        } catch (\Exception $e) {
            throw new BusinessException(lang('business.alipay_query_order_failed') . ': ' . $e->getMessage());
        }
    }

    public function refund(array $refund): array
    {
        try {
            $result = AlipayFactory::payment()->common()->refund(
                $refund['out_trade_no'],
                (string)$refund['refund_amount']
            );
            $data = json_decode($result->httpBody, true);
            $response = $data['alipay_trade_refund_response'] ?? [];

            if (($response['code'] ?? '') !== '10000') {
                throw new BusinessException(lang('business.alipay_refund_failed') . ': ' . ($response['sub_msg'] ?? $response['msg'] ?? lang('messages.unknown_error')));
            }

            return [
                'out_trade_no'  => $response['out_trade_no'] ?? '',
                'trade_no'      => $response['trade_no'] ?? '',
                'refund_amount' => $response['refund_fee'] ?? $refund['refund_amount'],
                'status'        => 'success',
                'raw'           => $response,
            ];
        } catch (BusinessException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new BusinessException(lang('business.alipay_refund_failed') . ': ' . $e->getMessage());
        }
    }

    public function verifyNotify(array $params): array
    {
        try {
            $verified = AlipayFactory::payment()->common()->verifyNotify($params);

            if (!$verified) {
                throw new BusinessException(lang('business.alipay_callback_verify_failed'));
            }

            return [
                'out_trade_no' => $params['out_trade_no'] ?? '',
                'trade_no'     => $params['trade_no'] ?? '',
                'total_amount' => $params['total_amount'] ?? '0',
                'status'       => $this->mapStatus($params['trade_status'] ?? ''),
                'raw'          => $params,
            ];
        } catch (BusinessException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new BusinessException(lang('business.alipay_callback_process_failed') . ': ' . $e->getMessage());
        }
    }

    public function successResponse(): string
    {
        return 'success';
    }

    public function getDriver(): string
    {
        return 'alipay';
    }

    protected function mapStatus(string $tradeStatus): string
    {
        return match ($tradeStatus) {
            'TRADE_SUCCESS', 'TRADE_FINISHED' => 'paid',
            'TRADE_CLOSED' => 'closed',
            'WAIT_BUYER_PAY' => 'pending',
            default => 'unknown',
        };
    }
}
