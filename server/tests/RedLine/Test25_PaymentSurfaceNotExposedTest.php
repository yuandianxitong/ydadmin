<?php

declare(strict_types=1);

namespace tests\RedLine;

use app\command\PaymentRefundCommand;
use app\middleware\ApiAuthMiddleware;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use tests\Support\ApiTestCase;
use tests\Support\RouteStack;
use Webman\Route;
use Webman\Route\Route as RouteObject;

/**
 * 红线（M5b spec §1.2、§9、§12「任意登录用户可退任意订单」）：
 *
 * 1. C 端不暴露通用下单与退款：1.x 的 /api/payment/refund 没有归属校验，任何登录用户都能退任意订单；
 *    /api/payment/create 让用户自定金额与业务类型。两条都**不注册**（404），不是「注册了但加校验」。
 * 2. /api/payment/* 的路由集合恰好是 query + 两个回调；只有两个回调是公开的，其余都挂 ApiAuthMiddleware。
 * 3. 退款只能由人在服务器上手动执行：payment:refund 不得进 cron 白名单——进了白名单，后台「定时任务」
 *    页任何有 system.cron_job.create 权限的管理员都能配一条定时退款。
 */
final class Test25_PaymentSurfaceNotExposedTest extends ApiTestCase
{
    /** @return array<string, array{string, string}> */
    public static function forbiddenEndpoints(): array
    {
        return [
            'POST create' => ['POST', '/api/payment/create'],
            'GET create'  => ['GET', '/api/payment/create'],
            'POST refund' => ['POST', '/api/payment/refund'],
            'GET refund'  => ['GET', '/api/payment/refund'],
        ];
    }

    #[DataProvider('forbiddenEndpoints')]
    public function test_create_and_refund_are_not_routed(string $method, string $uri): void
    {
        $user = $this->actingAsUser();
        $response = $this->call($method, $uri, ['order_no' => 'R20260101000000000001', 'amount' => '1.00', 'channel' => 'wechat'], $user->token);

        $this->assertSame(404, $response->status(), "{$method} {$uri} 必须落到 fallback（HTTP 404），不能被注册");
        $response->assertCode(404);
    }

    public function test_payment_route_set_is_exactly_query_and_two_notifies(): void
    {
        self::ensureRoutesLoaded();
        $routes = [];
        foreach (Route::getRoutes() as $route) {
            /** @var RouteObject $route */
            $path = $route->getPath();
            if (str_starts_with($path, '/api/') && (str_starts_with($path, '/api/payment') || str_contains($path, 'refund') || str_contains($path, 'recharge'))) {
                $routes[$path] = RouteStack::outerToInner($route);
            }
        }
        ksort($routes);

        $this->assertSame(
            ['/api/payment/notify/alipay', '/api/payment/notify/wechat', '/api/payment/query', '/api/user/recharge'],
            array_keys($routes),
            'C 端支付相关路由只允许这四条；任何带 refund 的 /api 路由都不允许'
        );
        $this->assertContains(ApiAuthMiddleware::class, $routes['/api/payment/query'], 'query 必须要求登录');
        $this->assertContains(ApiAuthMiddleware::class, $routes['/api/user/recharge'], 'recharge 必须要求登录');
        $this->assertNotContains(ApiAuthMiddleware::class, $routes['/api/payment/notify/wechat'], '微信回调由微信服务器发起，不能要求 C 端 token');
        $this->assertNotContains(ApiAuthMiddleware::class, $routes['/api/payment/notify/alipay'], '支付宝回调由支付宝服务器发起，不能要求 C 端 token');
    }

    public function test_refund_command_is_not_schedulable(): void
    {
        $commands = (array) config('cron.commands', []);

        $attributes = (new \ReflectionClass(PaymentRefundCommand::class))->getAttributes(AsCommand::class);
        $this->assertCount(1, $attributes, '前置条件：退款命令类存在且带 #[AsCommand]');
        $this->assertSame('payment:refund', $attributes[0]->newInstance()->name, '前置条件：退款命令名是 payment:refund');

        $this->assertArrayNotHasKey('payment:refund', $commands, 'payment:refund 不得进 cron 白名单');
        $this->assertNotContains(PaymentRefundCommand::class, $commands, '退款命令类不得以任何别名进 cron 白名单');

        // 对照：两个本该可调度的命令确实在白名单里，证明读到的是真实配置而不是空数组
        $this->assertArrayHasKey('payment:close-expired', $commands);
        $this->assertArrayHasKey('payment:reconcile-refunds', $commands);
    }
}
