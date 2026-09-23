<?php

declare(strict_types=1);

namespace app\adminapi\controller\payment;

use app\service\payment\PaymentAdminService;
use core\base\Controller;
use core\context\RequestContext;
use core\exception\BusinessException;
use core\payment\Money;
use core\permission\Permission;
use DI\Attribute\Inject;
use support\Response;
use Webman\Http\Request;

/**
 * 管理端充值订单。退款写路径复用 RefundService，操作人 admin:{id}。
 */
class PaymentOrderController extends Controller
{
    #[Inject]
    protected PaymentAdminService $paymentAdminService;

    #[Permission('payment.order.list')]
    public function index(Request $request): Response
    {
        [$page, $limit] = $this->pageParams($request);

        return $this->paginate($this->paymentAdminService->getList((array) $request->get(), $page, $limit));
    }

    #[Permission('payment.order.detail')]
    public function show(string $orderNo): Response
    {
        return $this->success($this->paymentAdminService->getDetail($orderNo), lang('messages.get_success'));
    }

    #[Permission('payment.order.refund')]
    public function refund(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->refundRules(), [
            'order_no.required' => 'payment.order_no_required',
            'amount.required'   => 'payment.amount_invalid',
            'amount.regex'      => 'payment.amount_invalid',
            'reason.max'        => 'payment.reason_max',
        ]);
        if (Money::toCents((string) $data['amount']) <= 0) {
            throw new BusinessException(lang('payment.amount_invalid'));
        }

        return $this->success($this->paymentAdminService->refund(
            (string) $data['order_no'],
            (string) $data['amount'],
            (string) ($data['reason'] ?? ''),
            RequestContext::actingUser()
        ));
    }

    /** @return array<string, string> */
    private function refundRules(): array
    {
        return [
            'order_no' => 'required|string|max:32',
            // 位数收到 8 位：payment_orders.amount_cents 是 int unsigned（上限 4294.96 万元），
            // 放行更长的数字会掉进 Money::toCents 的 15 位限制，抛出非业务异常变成 HTTP 500。
            'amount'   => 'required|regex:/^[0-9]{1,8}(\.[0-9]{1,2})?$/',
            // REASON_MAX_BYTES 是按字节截断的，max:80 按字符算，中文会被静默腰斩成 26 字
            'reason'   => 'nullable|string|max:26',
        ];
    }
}
