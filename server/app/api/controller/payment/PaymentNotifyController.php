<?php

declare(strict_types=1);

namespace app\api\controller\payment;

use app\service\payment\PaymentService;
use core\base\Controller;
use core\payment\Channel;
use core\payment\dto\NotifyRequest;
use core\permission\PermissionSkip;
use DI\Attribute\Inject;
use support\Response;
use Webman\Http\Request;

/**
 * 支付回调（spec §5.3）。公开端点：渠道凭签名证明身份，不挂 ApiAuthMiddleware，也不走统一响应体——
 * 微信要 HTTP 状态码 + JSON，支付宝要纯文本 success/fail，应答原样来自 PaymentService::handleNotify()。
 *
 * 控制器只负责把 webman 请求翻成 NotifyRequest：头名一律小写，body 原样（验签覆盖原始字节，不能重新编码），
 * form 是框架解析出的表单（支付宝验签用）。整段 try/catch 兜底：回调端点不允许冒出统一异常处理器的 JSON。
 */
class PaymentNotifyController extends Controller
{
    #[Inject]
    protected PaymentService $paymentService;

    #[PermissionSkip]
    public function wechat(Request $request): Response
    {
        return $this->handle(Channel::WECHAT, $request);
    }

    #[PermissionSkip]
    public function alipay(Request $request): Response
    {
        return $this->handle(Channel::ALIPAY, $request);
    }

    private function handle(string $channel, Request $request): Response
    {
        try {
            $headers = [];
            foreach ((array) $request->header() as $name => $value) {
                $headers[strtolower((string) $name)] = is_array($value) ? implode(',', $value) : (string) $value;
            }
            $form = $request->post();
            $ack = $this->paymentService->handleNotify(
                $channel,
                new NotifyRequest($headers, (string) $request->rawBody(), is_array($form) ? $form : [])
            );
        } catch (\Throwable) {
            $ack = $this->paymentService->notifyFailureAck($channel);
        }

        return new Response($ack->status, ['Content-Type' => $ack->contentType], $ack->body);
    }
}
