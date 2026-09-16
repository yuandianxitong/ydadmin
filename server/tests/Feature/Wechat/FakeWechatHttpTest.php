<?php

declare(strict_types=1);

namespace tests\Feature\Wechat;

use core\wechat\WechatHttpClient;
use support\Container;
use tests\Support\Wechat\FakeWechatHttp;
use tests\TestCase;

/**
 * 夹具自检：fakeWechatHttp() 换掉容器里的 WechatHttpClient，restoreWechatHttp() 换回不带假 handler 的实例。
 * 后续任务的服务测试都靠它隔离微信，夹具本身坏了会让那些测试悄悄触网或悄悄绿。
 */
final class FakeWechatHttpTest extends TestCase
{
    use FakeWechatHttp;

    protected function tearDown(): void
    {
        try {
            $this->restoreWechatHttp();
        } finally {
            parent::tearDown();
        }
    }

    public function test_fake_swaps_the_container_client_and_records_requests(): void
    {
        $this->fakeWechatHttp([self::wechatJson(['hello' => 'world'])]);

        $data = Container::get(WechatHttpClient::class)->get('sns/userinfo', ['openid' => 'o-1']);

        $this->assertSame(['hello' => 'world'], $data);
        $this->assertCount(1, $this->wechatRequests());
        $this->assertSame('/sns/userinfo', $this->wechatRequests()[0]['request']->getUri()->getPath());
    }

    public function test_restore_replaces_the_fake_with_a_fresh_real_client(): void
    {
        $this->fakeWechatHttp([]);
        $fake = Container::get(WechatHttpClient::class);

        $this->restoreWechatHttp();

        $real = Container::get(WechatHttpClient::class);
        $this->assertNotSame($fake, $real);
        $handler = (new \ReflectionProperty(WechatHttpClient::class, 'http'))->getValue($real)->getConfig('handler');
        $inner = $handler instanceof \GuzzleHttp\HandlerStack
            ? (new \ReflectionProperty(\GuzzleHttp\HandlerStack::class, 'handler'))->getValue($handler)
            : $handler;
        $this->assertNotInstanceOf(\GuzzleHttp\Handler\MockHandler::class, $inner, '还原后不得残留 MockHandler（取 HandlerStack 内层 handler 断言）');
    }

    public function test_history_is_reset_between_fakes(): void
    {
        $this->fakeWechatHttp([self::wechatJson(['a' => 1])]);
        Container::get(WechatHttpClient::class)->get('cgi-bin/token', []);
        $this->fakeWechatHttp([self::wechatJson(['b' => 2])]);

        $this->assertSame([], $this->wechatRequests());
    }
}
