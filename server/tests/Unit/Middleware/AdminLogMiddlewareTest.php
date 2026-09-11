<?php

declare(strict_types=1);

namespace tests\Unit\Middleware;

use app\middleware\AdminLogMiddleware;
use app\repository\system\AdminOperationLogRepository;
use core\context\RequestContext;
use core\response\Api;
use tests\Support\FakeConnection;
use tests\TestCase;
use Webman\Http\Request;
use Webman\Http\Response;

/** 记下每次 record() 的入参；$fail 为真时模拟写库失败。 */
final class SpyOperationLogRepository extends AdminOperationLogRepository
{
    /** @var list<array<string, mixed>> */
    public array $records = [];

    public function __construct(private readonly bool $fail = false)
    {
        parent::__construct();
    }

    public function record(array $data): void
    {
        if ($this->fail) {
            throw new \RuntimeException('数据库不可用');
        }
        $this->records[] = $data;
    }
}

final class DemoLogController
{
    public function store(): string
    {
        return 'ok';
    }
}

final class AdminLogMiddlewareTest extends TestCase
{
    /** @param array<string, mixed> $body */
    private function request(string $method, string $controller = DemoLogController::class, string $action = 'store', array $body = []): Request
    {
        $json = $body === [] ? '' : (string) json_encode($body, JSON_UNESCAPED_UNICODE);
        $request = new Request("{$method} /adminapi/demo/1 HTTP/1.1\r\nHost: localhost\r\nUser-Agent: phpunit\r\nContent-Type: application/json\r\nContent-Length: " . strlen($json) . "\r\n\r\n{$json}");
        $request->connection = new FakeConnection('10.9.8.7');
        $request->controller = $controller;
        $request->action = $action;
        $request->username = 'alice';

        return $request;
    }

    public function test_write_is_recorded_with_masked_params_and_the_response_code(): void
    {
        RequestContext::setActingUser(7);
        $spy = new SpyOperationLogRepository();

        (new AdminLogMiddleware($spy))->process(
            $this->request('POST', DemoLogController::class, 'store', ['username' => 'bob', 'password' => 'Secret#1', 'profile' => ['token' => 't', 'nickname' => 'n']]),
            static fn () => Api::error('用户名已存在', 400)
        );

        $this->assertCount(1, $spy->records);
        $record = $spy->records[0];
        $this->assertSame(7, $record['admin_id']);
        $this->assertSame('alice', $record['username']);
        $this->assertSame('POST', $record['method']);
        $this->assertSame('/adminapi/demo/1', $record['path']);
        $this->assertSame('10.9.8.7', $record['ip']);
        $this->assertSame('phpunit', $record['user_agent']);
        $this->assertSame(['username' => 'bob', 'password' => '***', 'profile' => ['token' => '***', 'nickname' => 'n']], $record['params']);
        $this->assertSame(['code' => 400, 'message' => '用户名已存在'], $record['result']);
        $this->assertIsFloat($record['execution_time']);
        $this->assertGreaterThanOrEqual(0.0, $record['execution_time']);
    }

    public function test_unmapped_action_falls_back_to_generic_text(): void
    {
        RequestContext::setActingUser(7);
        $spy = new SpyOperationLogRepository();

        (new AdminLogMiddleware($spy))->process($this->request('DELETE'), static fn () => Api::success([], '已执行'));

        $this->assertSame(lang('messages.operation'), $spy->records[0]['action']);
        $this->assertSame(lang('messages.execute_operation'), $spy->records[0]['description']);
        $this->assertSame(['code' => 200, 'message' => '已执行'], $spy->records[0]['result']);
    }

    public function test_mapped_action_resolves_its_lang_keys(): void
    {
        RequestContext::setActingUser(7);
        $spy = new SpyOperationLogRepository();

        (new AdminLogMiddleware($spy))->process($this->request('PUT', 'app\\adminapi\\controller\\system\\AdminController', 'update'), static fn () => Api::success());

        $this->assertSame(lang('admin_log.admin_update'), $spy->records[0]['action']);
        $this->assertSame(lang('admin_log.admin_update_desc'), $spy->records[0]['description']);
        $this->assertNotSame(lang('messages.operation'), $spy->records[0]['action']);
    }

    public function test_configured_fields_are_masked_whole_for_their_action(): void
    {
        RequestContext::setActingUser(7);
        $spy = new SpyOperationLogRepository();

        (new AdminLogMiddleware($spy))->process(
            $this->request('PUT', 'app\\adminapi\\controller\\system\\SystemConfigController', 'update', ['config_value' => 'plain-secret']),
            static fn () => Api::success(true)
        );

        $this->assertSame(['config_value' => '***'], $spy->records[0]['params']);
    }

    public function test_reads_and_anonymous_requests_are_not_recorded(): void
    {
        $spy = new SpyOperationLogRepository();
        $middleware = new AdminLogMiddleware($spy);

        RequestContext::setActingUser(7);
        $middleware->process($this->request('GET'), static fn () => Api::success());
        RequestContext::setActingUser(0);
        $middleware->process($this->request('POST'), static fn () => Api::success());

        $this->assertSame([], $spy->records);
    }

    public function test_a_failing_log_write_never_changes_the_response(): void
    {
        RequestContext::setActingUser(7);

        $response = (new AdminLogMiddleware(new SpyOperationLogRepository(true)))->process(
            $this->request('POST'),
            static fn () => Api::success(['id' => 1], '创建成功')
        );

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->rawBody(), true);
        $this->assertSame(200, $body['code']);
        $this->assertSame(['id' => 1], $body['data']);
    }

    public function test_non_json_response_falls_back_to_the_http_status(): void
    {
        RequestContext::setActingUser(7);
        $spy = new SpyOperationLogRepository();

        (new AdminLogMiddleware($spy))->process($this->request('POST'), static fn () => new Response(204, [], ''));

        $this->assertSame(['code' => 204, 'message' => ''], $spy->records[0]['result']);
    }

    public function test_short_key_strips_the_namespace(): void
    {
        $this->assertSame('AdminController@store', AdminLogMiddleware::shortKey('app\\adminapi\\controller\\system\\AdminController', 'store'));
        $this->assertSame('Bare@run', AdminLogMiddleware::shortKey('Bare', 'run'));
    }
}
