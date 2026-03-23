<?php
declare(strict_types=1);

namespace app\api\controller\v1\payment;

use core\base\Controller;
use app\service\payment\PaymentService;
use think\Response;

class PaymentController extends Controller
{
    protected PaymentService $paymentService;

    /**
     * 创建支付订单
     */
    public function create(): Response
    {
        try {
            $channel = (string)$this->request->param('channel', '');
            $params = $this->request->only([
                'subject', 'total_amount', 'trade_type',
                'return_url', 'quit_url', 'openid', 'client_ip',
            ]);

            if (empty($channel)) {
                return $this->error(lang('business.select_payment_channel'));
            }

            if (empty($params['total_amount']) || $params['total_amount'] <= 0) {
                return $this->error(lang('business.invalid_payment_amount'));
            }

            if (empty($params['subject'])) {
                return $this->error(lang('business.order_title_required'));
            }

            $result = $this->paymentService->createOrder($channel, $params);
            return $this->success(lang('messages.create_success'), $result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 查询订单
     */
    public function query(): Response
    {
        try {
            $orderNo = (string)$this->request->param('order_no', '');
            if (empty($orderNo)) {
                return $this->error(lang('business.params_incomplete'));
            }

            // 从订单记录自动获取 channel，前端无需传
            $channel = (string)$this->request->param('channel', '');
            if (empty($channel)) {
                $order = \app\model\payment\PaymentOrder::where('order_no', $orderNo)->find();
                if (!$order) {
                    return $this->error('订单不存在');
                }
                $channel = $order->channel;
            }

            $result = $this->paymentService->queryOrder($channel, $orderNo);
            return $this->success(lang('messages.query_success'), $result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 退款
     */
    public function refund(): Response
    {
        try {
            $channel = (string)$this->request->param('channel', '');
            $orderNo = (string)$this->request->param('order_no', '');
            $refundAmount = (float)$this->request->param('refund_amount', 0);
            $reason = (string)$this->request->param('reason', lang('business.user_refund_reason'));

            if (empty($channel) || empty($orderNo)) {
                return $this->error(lang('business.params_incomplete'));
            }

            if ($refundAmount <= 0) {
                return $this->error(lang('business.refund_amount_positive'));
            }

            $result = $this->paymentService->refund($channel, [
                'out_trade_no'  => $orderNo,
                'refund_amount' => $refundAmount,
                'reason'        => $reason,
            ]);

            return $this->success(lang('messages.refund_success'), $result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 支付宝异步回调
     */
    public function alipayNotify(): Response
    {
        $params = $this->request->param();
        $result = $this->paymentService->handleNotify('alipay', $params);

        return response($result, 200, ['Content-Type' => 'text/plain']);
    }

    /**
     * 微信支付异步回调
     */
    public function wechatNotify(): Response
    {
        $body = $this->request->getContent();
        $params = json_decode($body, true) ?: [];

        // 传递签名头信息用于验签
        $params['_headers'] = [
            'Wechatpay-Timestamp' => $this->request->header('Wechatpay-Timestamp', ''),
            'Wechatpay-Nonce'     => $this->request->header('Wechatpay-Nonce', ''),
            'Wechatpay-Signature' => $this->request->header('Wechatpay-Signature', ''),
            'Wechatpay-Serial'    => $this->request->header('Wechatpay-Serial', ''),
        ];
        $params['_body'] = $body;

        $result = $this->paymentService->handleNotify('wechat', $params);

        return response($result, 200, ['Content-Type' => 'application/json']);
    }
}
