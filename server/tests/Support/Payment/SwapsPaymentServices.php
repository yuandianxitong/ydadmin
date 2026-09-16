<?php

declare(strict_types=1);

namespace tests\Support\Payment;

use app\service\payment\OrderNoGenerator;
use core\payment\GatewayResolver;
use core\payment\PaymentManager;
use support\Container;

/**
 * 支付服务是 #[Inject] 注入依赖的容器单例：别的用例先解析过它，之后再 Container::set() 换依赖不会生效。
 * 所以换完依赖要按依赖顺序重建这些单例（后续任务的服务类存在时自动纳入）。控制器是每请求 make 的
 * （config/app.php controller_reuse=false），经 HTTP 的测试拿到的就是重建后的服务。
 */
trait SwapsPaymentServices
{
    /** 按依赖顺序：RechargeService / RefundService 注入 PaymentService，要在它之后重建 */
    private const PAYMENT_SERVICE_CLASSES = [
        'app\service\payment\PaymentService',
        'app\service\payment\RechargeService',
        'app\service\payment\RefundService',
    ];

    private function swapPaymentDependency(string $id, object $instance): void
    {
        Container::set($id, $instance);
        $this->rebuildPaymentServices();
    }

    private function restorePaymentDependencies(): void
    {
        Container::set(GatewayResolver::class, Container::get(PaymentManager::class));
        Container::set(OrderNoGenerator::class, new OrderNoGenerator());
        $this->rebuildPaymentServices();
    }

    private function rebuildPaymentServices(): void
    {
        foreach (self::PAYMENT_SERVICE_CLASSES as $class) {
            if (class_exists($class)) {
                Container::set($class, Container::make($class));
            }
        }
    }
}
