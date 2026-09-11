<?php

declare(strict_types=1);

namespace tests\Support;

use Workerman\Connection\TcpConnection;

/**
 * 测试用连接替身。Workerman 的 Request::$connection 类型为 ?TcpConnection，所以必须继承它；
 * 不调用父构造（不需要真实 socket 与事件循环），只接住 webman App::send() 写出的响应。
 */
final class FakeConnection extends TcpConnection
{
    public mixed $response = null;

    public bool $closed = false;

    public function __construct(private readonly string $fakeRemoteIp = '127.0.0.1')
    {
    }

    public function send(mixed $sendBuffer, bool $raw = false): bool|null
    {
        $this->response = $sendBuffer;

        return true;
    }

    public function close(mixed $data = null, bool $raw = false): void
    {
        if ($data !== null) {
            $this->response = $data;
        }
        $this->closed = true;
    }

    public function getRemoteIp(): string
    {
        return $this->fakeRemoteIp;
    }

    public function getRemotePort(): int
    {
        return 50000;
    }

    public function __destruct()
    {
        // 父类析构依赖真实连接的统计字段，替身不需要
    }
}
