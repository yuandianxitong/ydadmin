<?php

declare(strict_types=1);

namespace tests\Unit\Http;

use core\http\ClientIp;
use support\Request;
use tests\Support\FakeConnection;
use tests\TestCase;

/** 可信代理解析客户端 IP：只有直连地址是可信代理时才读 X-Forwarded-For，且从右往左取第一个非代理地址。 */
final class ClientIpTest extends TestCase
{
    private const PROXIES = ['10.0.0.1', '10.0.0.2'];

    private ?string $originalTrustedProxies = null;

    protected function tearDown(): void
    {
        $this->setOrUnsetEnv('TRUSTED_PROXIES', $this->originalTrustedProxies);
        parent::tearDown();
    }

    private function request(string $remoteIp, ?string $forwardedFor = null): Request
    {
        $raw = "GET / HTTP/1.1\r\nHost: localhost\r\nX-Real-IP: 6.6.6.6\r\n";
        if ($forwardedFor !== null) {
            $raw .= "X-Forwarded-For: {$forwardedFor}\r\n";
        }
        $request = new Request($raw . "\r\n");
        $request->connection = new FakeConnection($remoteIp);

        return $request;
    }

    public function test_untrusted_remote_ignores_spoofed_forwarded_for(): void
    {
        $this->assertSame('203.0.113.9', ClientIp::resolve($this->request('203.0.113.9', '1.1.1.1'), self::PROXIES));
        // 回环/私网直连也一样——webman 的 getRealIp() 恰恰会信任这类地址发来的 X-Forwarded-For
        $this->assertSame('127.0.0.1', ClientIp::resolve($this->request('127.0.0.1', '1.1.1.1'), self::PROXIES));
    }

    public function test_trusted_remote_takes_the_rightmost_forwarded_entry(): void
    {
        $this->assertSame('2.2.2.2', ClientIp::resolve($this->request('10.0.0.1', '1.1.1.1, 2.2.2.2'), self::PROXIES));
    }

    public function test_trusted_proxies_in_the_chain_are_skipped(): void
    {
        $this->assertSame('9.9.9.9', ClientIp::resolve($this->request('10.0.0.1', '9.9.9.9, 10.0.0.2'), self::PROXIES));
    }

    public function test_garbage_or_missing_forwarded_for_falls_back_to_the_remote(): void
    {
        foreach (['garbage', '', ' , ', '10.0.0.2', '1.1.1.1, not-an-ip'] as $forwardedFor) {
            $this->assertSame('10.0.0.1', ClientIp::resolve($this->request('10.0.0.1', $forwardedFor), self::PROXIES), "X-Forwarded-For: {$forwardedFor}");
        }
        // 没有 X-Forwarded-For 时不退而求其次去读 X-Real-IP
        $this->assertSame('10.0.0.1', ClientIp::resolve($this->request('10.0.0.1'), self::PROXIES));
    }

    public function test_trusted_list_defaults_to_config(): void
    {
        $this->assertSame([], config('proxy.trusted_proxies'), '测试环境不配置可信代理');
        $this->assertSame('127.0.0.1', ClientIp::resolve($this->request('127.0.0.1', '1.1.1.1')));
    }

    public function test_config_parses_a_trimmed_comma_separated_list(): void
    {
        $this->originalTrustedProxies = $_ENV['TRUSTED_PROXIES'] ?? null;

        $this->setOrUnsetEnv('TRUSTED_PROXIES', ' 10.0.0.1 , ,10.0.0.2 ');
        $this->assertSame(['10.0.0.1', '10.0.0.2'], (require base_path() . '/config/proxy.php')['trusted_proxies']);

        $this->setOrUnsetEnv('TRUSTED_PROXIES', null);
        $this->assertSame([], (require base_path() . '/config/proxy.php')['trusted_proxies']);
    }

    private function setOrUnsetEnv(string $key, ?string $value): void
    {
        if ($value === null) {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);
        } else {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}
