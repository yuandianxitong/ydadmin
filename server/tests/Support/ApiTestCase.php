<?php

declare(strict_types=1);

namespace tests\Support;

use support\Log;
use support\Request;
use tests\TestCase;
use Webman\App;

/**
 * 接口测试基类：请求经 Webman\App::onMessage() 完整执行，覆盖路由、中间件顺序、
 * 异常处理、fallback 与每请求的 Context 销毁。
 */
abstract class ApiTestCase extends TestCase
{
    /** 进程内共享的 App 实例（测试代码，不受 check:context 约束） */
    private static ?App $app = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::ensureRoutesLoaded();
        self::$app ??= new App(Request::class, Log::channel('default'), app_path(), public_path());
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    protected function call(string $method, string $uri, array $data = [], ?string $token = null, array $headers = []): TestResponse
    {
        $method = strtoupper($method);
        $body = '';
        if ($method === 'GET' || $method === 'DELETE') {
            if ($data !== []) {
                $uri .= (str_contains($uri, '?') ? '&' : '?') . http_build_query($data);
            }
        } else {
            $body = (string) json_encode($data, JSON_UNESCAPED_UNICODE);
            $headers['Content-Type'] = 'application/json';
        }

        $headers += ['Host' => 'localhost', 'Accept' => 'application/json'];
        if ($token !== null) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }
        $headers['Content-Length'] = (string) strlen($body);

        $raw = "{$method} {$uri} HTTP/1.1\r\n";
        foreach ($headers as $name => $value) {
            $raw .= "{$name}: {$value}\r\n";
        }
        $raw .= "\r\n" . $body;

        $connection = new FakeConnection();
        $request = new Request($raw);
        $request->connection = $connection;

        /** @var App $app */
        $app = self::$app;
        $app->onMessage($connection, $request);

        return new TestResponse($connection->response);
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, string> $headers
     */
    protected function get(string $uri, array $query = [], ?string $token = null, array $headers = []): TestResponse
    {
        return $this->call('GET', $uri, $query, $token, $headers);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    protected function post(string $uri, array $data = [], ?string $token = null, array $headers = []): TestResponse
    {
        return $this->call('POST', $uri, $data, $token, $headers);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    protected function put(string $uri, array $data = [], ?string $token = null, array $headers = []): TestResponse
    {
        return $this->call('PUT', $uri, $data, $token, $headers);
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, string> $headers
     */
    protected function delete(string $uri, array $query = [], ?string $token = null, array $headers = []): TestResponse
    {
        return $this->call('DELETE', $uri, $query, $token, $headers);
    }
}
