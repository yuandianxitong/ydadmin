<?php
declare(strict_types=1);

namespace app\service\payment;

use app\model\payment\PaymentOrder;
use app\repository\payment\PaymentOrderRepository;
use app\repository\user\UserRepository;
use core\base\Service;
use core\exception\BusinessException;
use core\payment\PaymentManager;
use think\facade\Db;
use think\facade\Log;

class PaymentService extends Service
{
    protected PaymentOrderRepository $orderRepository;
    protected UserRepository $userRepository;

    /**
     * 创建支付订单
     * @param string $channel alipay / wechat
     * @param array $params [out_trade_no, total_amount, subject, body, trade_type, notify_url, ...]
     * @return array
     */
    public function createOrder(string $channel, array $params): array
    {
        $orderNo = $params['out_trade_no'] ?? $this->generateOrderNo($channel);

        // 记录支付订单
        $paymentOrder = $this->orderRepository->createOrder([
            'order_no'     => $orderNo,
            'channel'      => $channel,
            'trade_type'   => $params['trade_type'] ?? 'native',
            'subject'      => $params['subject'] ?? '',
            'body'         => $params['body'] ?? '',
            'total_amount' => $params['total_amount'],
            'status'       => PaymentOrder::STATUS_PENDING,
            'user_id'      => $params['user_id'] ?? null,
            'biz_type'     => $params['biz_type'] ?? null,
            'client_type'  => $params['client_type'] ?? null,
        ]);

        // 调用支付驱动
        $params['out_trade_no'] = $orderNo;
        $driver = PaymentManager::channel($channel);
        $result = $driver->create($params);

        return [
            'order_no'      => $orderNo,
            'payment_id'    => $paymentOrder->id,
            'payment_data'  => $result,
        ];
    }

    /**
     * 查询订单状态
     */
    public function queryOrder(string $channel, string $orderNo): array
    {
        $driver = PaymentManager::channel($channel);
        $result = $driver->query($orderNo);

        // 如果状态变化，同步更新本地记录
        $paymentOrder = $this->orderRepository->findByOrderNo($orderNo);
        if ($paymentOrder && $paymentOrder->status !== $result['status'] && $result['status'] !== 'unknown') {
            $updateData = [
                'status'   => $result['status'],
                'trade_no' => $result['trade_no'] ?? '',
            ];
            if ($result['status'] === 'paid') {
                $updateData['paid_at'] = date('Y-m-d H:i:s');
            }
            $this->orderRepository->updateByOrderNo($orderNo, $updateData);
        }

        return $result;
    }

    /**
     * 退款
     */
    public function refund(string $channel, array $params): array
    {
        if (empty($params['out_trade_no'])) {
            throw new BusinessException(lang('business.order_no_required'));
        }

        if (empty($params['refund_amount']) || (float) $params['refund_amount'] <= 0) {
            throw new BusinessException(lang('business.refund_amount_positive'));
        }

        $paymentOrder = $this->orderRepository->findByOrderNo($params['out_trade_no']);
        if (!$paymentOrder) {
            throw new BusinessException(lang('business.payment_order_not_found'));
        }

        if ($paymentOrder->status !== PaymentOrder::STATUS_PAID) {
            throw new BusinessException(lang('business.order_status_no_refund'));
        }

        if ((float) $params['refund_amount'] > (float) $paymentOrder->total_amount) {
            throw new BusinessException(lang('business.refund_exceed_amount'));
        }

        $params['total_amount'] = $paymentOrder->total_amount;

        $driver = PaymentManager::channel($channel);
        $result = $driver->refund($params);

        // 更新订单状态
        if ($result['status'] === 'success') {
            $this->orderRepository->updateByOrderNo($params['out_trade_no'], [
                'status'        => PaymentOrder::STATUS_REFUNDED,
                'refund_amount' => (float) $params['refund_amount'],
                'refunded_at'   => date('Y-m-d H:i:s'),
            ]);
        }

        return $result;
    }

    /**
     * 处理支付回调
     */
    public function handleNotify(string $channel, array $params): string
    {
        $driver = PaymentManager::channel($channel);

        try {
            $data = $driver->verifyNotify($params);
        } catch (\Exception $e) {
            Log::error("支付回调验签失败 [{$channel}]: " . $e->getMessage());
            return 'fail';
        }

        // 使用事务 + 行锁防止并发回调重复处理
        Db::startTrans();
        try {
            $paymentOrder = $this->orderRepository->findByOrderNoForUpdate($data['out_trade_no']);
            if (!$paymentOrder) {
                Db::rollback();
                Log::error("支付回调找不到订单: " . $data['out_trade_no']);
                return 'fail';
            }

            // 幂等：已处理过的订单直接返回成功
            if ($paymentOrder->status === PaymentOrder::STATUS_PAID) {
                Db::rollback();
                return $driver->successResponse();
            }

            // 更新订单
            if ($data['status'] === 'paid') {
                $paymentOrder->status = PaymentOrder::STATUS_PAID;
                $paymentOrder->trade_no = $data['trade_no'];
                $paymentOrder->paid_at = date('Y-m-d H:i:s');
                $paymentOrder->notify_data = json_encode($data['raw'], JSON_UNESCAPED_UNICODE);
                $paymentOrder->save();
            }

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            Log::error("支付回调处理异常 [{$channel}]: " . $e->getMessage());
            return 'fail';
        }

        // 事务提交后触发事件（避免事件处理器失败导致回滚）
        if ($data['status'] === 'paid' && isset($paymentOrder) && $paymentOrder->status === PaymentOrder::STATUS_PAID) {
            $this->trigger('payment.success', [
                'order_no' => $data['out_trade_no'],
                'trade_no' => $data['trade_no'],
                'amount'   => $data['total_amount'],
                'channel'  => $channel,
                'user_id'  => $paymentOrder->user_id,
                'biz_type' => $paymentOrder->biz_type,
            ]);
        }

        return $driver->successResponse();
    }

    /**
     * 根据订单号获取支付渠道
     */
    public function getChannelByOrderNo(string $orderNo): ?string
    {
        $order = $this->orderRepository->findByOrderNo($orderNo);
        return $order?->channel;
    }

    /**
     * 生成商户订单号
     */
    protected function generateOrderNo(string $channel): string
    {
        $prefix = match ($channel) {
            'alipay' => 'A',
            'wechat' => 'W',
            default  => 'P',
        };

        return $prefix . date('YmdHis') . str_pad((string) mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * 根据客户端类型解析微信支付参数
     */
    public function resolveWechatPayParams(string $clientType, int $userId): array
    {
        $config = PaymentManager::getWechatConfig();
        $defaultAppId = $config['app_id']; // pay_wechat_app_id 兜底

        return match ($clientType) {
            'miniapp'    => [
                'trade_type' => 'jsapi',
                'appid'      => $config['mini_app_id'] ?: $defaultAppId,
                'openid'     => $this->getUserOpenid($userId, 'mini_openid'),
            ],
            'wechat_h5'  => [
                'trade_type' => 'jsapi',
                'appid'      => $config['official_app_id'] ?: $defaultAppId,
                'openid'     => $this->getUserOpenid($userId, 'oa_openid'),
            ],
            'h5'         => [
                'trade_type' => 'h5',
                'appid'      => $config['official_app_id'] ?: $defaultAppId,
                'openid'     => null,
            ],
            'app'        => [
                'trade_type' => 'app',
                'appid'      => $config['mobile_app_id'] ?: $defaultAppId,
                'openid'     => null,
            ],
            'pc'         => [
                'trade_type' => 'native',
                'appid'      => $config['open_app_id'] ?: $defaultAppId,
                'openid'     => null,
            ],
            default => throw new BusinessException('不支持的客户端类型: ' . $clientType),
        };
    }

    protected function getUserOpenid(int $userId, string $field): string
    {
        $openid = $this->userRepository->getFieldById($userId, $field) ?? '';
        if (empty($openid)) {
            throw new BusinessException('请先完成微信授权后再支付');
        }
        return $openid;
    }
}
