<?php
declare(strict_types=1);
namespace app\listener\payment;

use app\model\user\BalanceLog;
use app\repository\user\UserRepository;
use app\service\message\MessageService;
use app\service\user\UserManageService;
use think\facade\Log;

class PaymentSuccessListener
{
    public function handle(array $event): void
    {
        Log::info('支付成功', [
            'order_no' => $event['order_no'],
            'trade_no' => $event['trade_no'],
            'amount'   => $event['amount'],
            'channel'  => $event['channel'],
        ]);

        $bizType = $event['biz_type'] ?? '';

        switch ($bizType) {
            case 'recharge':
                $this->handleRecharge($event);
                break;
            default:
                Log::info('未处理的业务类型', ['biz_type' => $bizType]);
                break;
        }

        // 发送支付成功通知
        $this->sendPaymentNotification($event);
    }

    protected function handleRecharge(array $event): void
    {
        $userId = (int) ($event['user_id'] ?? 0);
        $amount = (float) ($event['amount'] ?? 0);
        $orderNo = $event['order_no'] ?? '';

        if (!$userId || !$amount || !$orderNo) {
            Log::error('充值回调参数不完整', $event);
            return;
        }

        try {
            $service = app(UserManageService::class);
            $service->adjustBalance(
                $userId, $amount, '在线充值',
                BalanceLog::TYPE_RECHARGE,
                'payment:' . $orderNo
            );
            Log::info('充值成功', ['user_id' => $userId, 'amount' => $amount, 'order_no' => $orderNo]);
        } catch (\Throwable $e) {
            Log::error('充值处理失败: ' . $e->getMessage(), ['user_id' => $userId, 'order_no' => $orderNo]);
        }
    }

    protected function sendPaymentNotification(array $event): void
    {
        $userId = (int) ($event['user_id'] ?? 0);
        if (!$userId) {
            return;
        }

        $user = app(UserRepository::class)->findModel($userId);
        if (!$user) {
            return;
        }

        $receivers = array_filter([
            'phone'       => $user->mobile ?? '',
            'openid'      => $user->oa_openid ?? '',
            'mini_openid' => $user->mini_openid ?? '',
        ]);

        if (!empty($receivers)) {
            app(MessageService::class)->trySend('payment_success', $receivers, [
                'amount'   => (string) ($event['amount'] ?? ''),
                'order_no' => $event['order_no'] ?? '',
            ]);
        }
    }
}
