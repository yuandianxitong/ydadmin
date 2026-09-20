<?php

declare(strict_types=1);

namespace tests\Support\Wechat;

use core\wechat\WechatHttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use support\Container;

/**
 * 微信 HTTP 离线夹具。
 *
 * - wechatClient()：只造一个带 MockHandler + history 的客户端，给直接 new 被测类的单元测试用，不动容器。
 * - fakeWechatHttp()：把容器里的 WechatHttpClient 换成假客户端，并按依赖顺序重建依赖它的容器单例
 *   （#[Inject] / 构造注入在首次解析时就定死了依赖，之后 Container::set() 换依赖对已存在的单例无效）。
 *   后续任务的类存在时自动纳入（class_exists 判定）。
 * - 用例 tearDown 里必须调 restoreWechatHttp()（放进 try/finally）。
 *
 * MockHandler 队列耗尽后再被调用会抛 OutOfBoundsException——「不该调微信却调了」会直接红，不会悄悄触网。
 */
trait FakeWechatHttp
{
    /** @var array<int, array<string, mixed>> Guzzle history 记录（每项含 request / response / error / options） */
    private array $wechatHistory = [];

    /** 依赖 WechatHttpClient 的容器单例，按依赖顺序（MiniProgramApi 与消息通道依赖 AccessTokenProvider，WechatAuthService 依赖前三者） */
    private const WECHAT_DEPENDENT_CLASSES = [
        'core\wechat\AccessTokenProvider',
        'core\wechat\MiniProgramApi',
        'core\wechat\OfficialAccountApi',
        'core\wechat\OAuthApi',
        'core\message\channel\WechatOfficialChannel',
        'core\message\channel\WechatMiniChannel',
        'app\service\wechat\WechatAuthService',
    ];

    /** @param list<Response|\Throwable> $responses */
    private function wechatClient(array $responses): WechatHttpClient
    {
        $this->wechatHistory = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->wechatHistory));

        return new WechatHttpClient($stack);
    }

    /** @param list<Response|\Throwable> $responses */
    private function fakeWechatHttp(array $responses): void
    {
        Container::set(WechatHttpClient::class, $this->wechatClient($responses));
        $this->rebuildWechatDependents();
    }

    /** @return array<int, array<string, mixed>> */
    private function wechatRequests(): array
    {
        return $this->wechatHistory;
    }

    private function restoreWechatHttp(): void
    {
        Container::set(WechatHttpClient::class, new WechatHttpClient());
        $this->rebuildWechatDependents();
    }

    /** @param array<string, mixed> $body */
    private static function wechatJson(array $body, int $status = 200): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], (string) json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function rebuildWechatDependents(): void
    {
        foreach (self::WECHAT_DEPENDENT_CLASSES as $class) {
            if (class_exists($class)) {
                Container::set($class, Container::make($class));
            }
        }
    }
}
