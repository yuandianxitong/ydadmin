<?php

declare(strict_types=1);

namespace app\api\controller\payment;

use app\service\payment\PaymentService;
use core\base\Controller;
use core\permission\PermissionSkip;
use DI\Attribute\Inject;
use support\Response;
use Webman\Http\Request;

/**
 * C 端支付订单查询（M5b spec §5.4）。挂 ApiAuthMiddleware，身份取自 $request->userId。
 * 只能查自己的订单：非本人与不存在同一个 404，不泄漏订单是否存在。
 * 回调是另一个公开控制器（PaymentNotifyController），不放在这里。
 */
class PaymentController extends Controller
{
    #[Inject]
    protected PaymentService $paymentService;

    #[PermissionSkip]
    public function query(Request $request): Response
    {
        $data = $this->validate((array) $request->get(), $this->queryRules(), $this->queryMessages());

        return $this->success(
            $this->paymentService->queryForUser((string) $data['order_no'], (int) $request->userId),
            lang('messages.get_success')
        );
    }

    /** @return array<string, string> */
    private function queryRules(): array
    {
        return [
            'order_no' => 'required|string|max:32',
        ];
    }

    /** @return array<string, string> */
    private function queryMessages(): array
    {
        return [
            'order_no.required' => 'validation.order_no_require',
            'order_no.string'   => 'validation.order_no_require',
            'order_no.max'      => 'validation.order_no_require',
        ];
    }
}
