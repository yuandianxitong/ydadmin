<?php
declare(strict_types=1);

namespace app\api\controller\v1\payment;

use core\base\Controller;
use app\service\payment\PaymentService;
use think\Response;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '支付', description: '支付订单创建、查询、退款及异步回调')]
class PaymentController extends Controller
{
    protected PaymentService $paymentService;

    #[OA\Post(
        path: '/payment/create',
        summary: '创建支付订单',
        security: [['bearerAuth' => []]],
        tags: ['支付'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['channel', 'subject', 'total_amount'],
                properties: [
                    new OA\Property(property: 'channel', type: 'string', description: '支付渠道（wechat/alipay）'),
                    new OA\Property(property: 'subject', type: 'string', description: '订单标题'),
                    new OA\Property(property: 'total_amount', type: 'number', description: '支付金额（元）'),
                    new OA\Property(property: 'trade_type', type: 'string', description: '微信交易类型（jsapi/h5/app/native）'),
                    new OA\Property(property: 'openid', type: 'string', description: 'JSAPI 支付时用户 openid'),
                    new OA\Property(property: 'return_url', type: 'string', description: '支付宝同步回跳地址'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '创建成功，返回支付参数', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
        ]
    )]
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

    #[OA\Get(
        path: '/payment/query',
        summary: '查询订单支付状态',
        security: [['bearerAuth' => []]],
        tags: ['支付'],
        parameters: [
            new OA\Parameter(name: 'order_no', in: 'query', required: true, description: '订单号', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: '查询成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
        ]
    )]
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
                $channel = $this->paymentService->getChannelByOrderNo($orderNo);
                if (empty($channel)) {
                    return $this->error('订单不存在');
                }
            }

            $result = $this->paymentService->queryOrder($channel, $orderNo);
            return $this->success(lang('messages.query_success'), $result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    #[OA\Post(
        path: '/payment/refund',
        summary: '申请退款',
        security: [['bearerAuth' => []]],
        tags: ['支付'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['channel', 'order_no', 'refund_amount'],
                properties: [
                    new OA\Property(property: 'channel', type: 'string', description: '支付渠道'),
                    new OA\Property(property: 'order_no', type: 'string', description: '订单号'),
                    new OA\Property(property: 'refund_amount', type: 'number', description: '退款金额（元）'),
                    new OA\Property(property: 'reason', type: 'string', description: '退款原因'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '退款申请成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
        ]
    )]
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

    #[OA\Post(
        path: '/payment/notify/alipay',
        summary: '支付宝异步回调（供支付宝服务器调用）',
        tags: ['支付'],
        responses: [
            new OA\Response(response: 200, description: 'success'),
        ]
    )]
    public function alipayNotify(): Response
    {
        $params = $this->request->param();
        $result = $this->paymentService->handleNotify('alipay', $params);

        return response($result, 200, ['Content-Type' => 'text/plain']);
    }

    #[OA\Post(
        path: '/payment/notify/wechat',
        summary: '微信支付异步回调（供微信服务器调用）',
        tags: ['支付'],
        responses: [
            new OA\Response(response: 200, description: '{"code":"SUCCESS"}'),
        ]
    )]
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
