<?php

declare(strict_types=1);

namespace tests\Unit\Middleware;

use app\middleware\AdminLogMiddleware;
use app\repository\system\AdminOperationLogRepository;
use core\context\RequestContext;
use core\queue\QueueDispatcher;
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

/** 记下每次 dispatch() 的队列名与载荷；$fail 为真时模拟 Redis 不可用。 */
final class SpyQueueDispatcher extends QueueDispatcher
{
    /** @var list<array{queue: string, data: array<string, mixed>}> */
    public array $dispatched = [];

    public function __construct(private readonly bool $fail = false)
    {
    }

    public function dispatch(string $queue, array $data): void
    {
        if ($this->fail) {
            throw new \RuntimeException('Redis 不可用');
        }
        $this->dispatched[] = ['queue' => $queue, 'data' => $data];
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

    /** @return array<string, mixed> 唯一一条投递的载荷（同时断言队列名） */
    private function onlyPayload(SpyQueueDispatcher $dispatcher): array
    {
        $this->assertCount(1, $dispatcher->dispatched);
        $this->assertSame('operation-log', $dispatcher->dispatched[0]['queue']);

        return $dispatcher->dispatched[0]['data'];
    }

    public function test_write_is_dispatched_with_masked_params_and_the_response_code(): void
    {
        RequestContext::setActingUser(7);
        $repository = new SpyOperationLogRepository();
        $dispatcher = new SpyQueueDispatcher();

        (new AdminLogMiddleware($repository, $dispatcher))->process(
            $this->request('POST', DemoLogController::class, 'store', ['username' => 'bob', 'password' => 'Secret#1', 'profile' => ['token' => 't', 'nickname' => 'n']]),
            static fn () => Api::error('用户名已存在', 400)
        );

        $record = $this->onlyPayload($dispatcher);
        $this->assertSame([], $repository->records, '投递成功时中间件不得再同步写库');
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
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $record['operation_time']);
        $this->assertNotFalse(json_encode($record), '载荷必须可 JSON 编码（redis 驱动要序列化它）');
    }

    public function test_operation_time_is_the_request_start_not_the_write_time(): void
    {
        RequestContext::setActingUser(7);
        $dispatcher = new SpyQueueDispatcher();
        $before = date('Y-m-d H:i:s');
        $afterHandler = '';

        (new AdminLogMiddleware(new SpyOperationLogRepository(), $dispatcher))->process(
            $this->request('POST'),
            static function () use (&$afterHandler) {
                usleep(1_100_000);
                $afterHandler = date('Y-m-d H:i:s');

                return Api::success();
            }
        );

        $operationTime = (string) $this->onlyPayload($dispatcher)['operation_time'];
        $this->assertGreaterThanOrEqual($before, $operationTime);
        $this->assertLessThan($afterHandler, $operationTime, 'operation_time 必须取请求开始时刻，而不是处理完、投递时的时刻');
    }

    public function test_unmapped_action_falls_back_to_generic_text(): void
    {
        RequestContext::setActingUser(7);
        $dispatcher = new SpyQueueDispatcher();

        (new AdminLogMiddleware(new SpyOperationLogRepository(), $dispatcher))->process($this->request('DELETE'), static fn () => Api::success([], '已执行'));

        $record = $this->onlyPayload($dispatcher);
        $this->assertSame(lang('messages.operation'), $record['action']);
        $this->assertSame(lang('messages.execute_operation'), $record['description']);
        $this->assertSame(['code' => 200, 'message' => '已执行'], $record['result']);
    }

    public function test_mapped_action_resolves_its_lang_keys(): void
    {
        RequestContext::setActingUser(7);
        $dispatcher = new SpyQueueDispatcher();

        (new AdminLogMiddleware(new SpyOperationLogRepository(), $dispatcher))->process($this->request('PUT', 'app\\adminapi\\controller\\system\\AdminController', 'update'), static fn () => Api::success());

        $record = $this->onlyPayload($dispatcher);
        $this->assertSame(lang('admin_log.admin_update'), $record['action']);
        $this->assertSame(lang('admin_log.admin_update_desc'), $record['description']);
        $this->assertNotSame(lang('messages.operation'), $record['action']);
    }

    public function test_configured_fields_are_masked_whole_for_their_action(): void
    {
        RequestContext::setActingUser(7);
        $dispatcher = new SpyQueueDispatcher();

        (new AdminLogMiddleware(new SpyOperationLogRepository(), $dispatcher))->process(
            $this->request('PUT', 'app\\adminapi\\controller\\system\\SystemConfigController', 'update', ['config_value' => 'plain-secret']),
            static fn () => Api::success(true)
        );

        $this->assertSame(['config_value' => '***'], $this->onlyPayload($dispatcher)['params']);
    }

    public function test_reads_and_anonymous_requests_are_not_recorded(): void
    {
        $repository = new SpyOperationLogRepository();
        $dispatcher = new SpyQueueDispatcher();
        $middleware = new AdminLogMiddleware($repository, $dispatcher);

        RequestContext::setActingUser(7);
        $middleware->process($this->request('GET'), static fn () => Api::success());
        RequestContext::setActingUser(0);
        $middleware->process($this->request('POST'), static fn () => Api::success());

        $this->assertSame([], $dispatcher->dispatched);
        $this->assertSame([], $repository->records);
    }

    public function test_dispatch_failure_falls_back_to_a_synchronous_write(): void
    {
        RequestContext::setActingUser(7);
        $repository = new SpyOperationLogRepository();

        (new AdminLogMiddleware($repository, new SpyQueueDispatcher(true)))->process(
            $this->request('POST', DemoLogController::class, 'store', ['password' => 'Secret#1']),
            static fn () => Api::success([], '创建成功')
        );

        $this->assertCount(1, $repository->records, 'Redis 不可用时必须退回同步写库，日志不丢');
        $this->assertSame(7, $repository->records[0]['admin_id']);
        $this->assertSame(['password' => '***'], $repository->records[0]['params']);
        $this->assertSame(['code' => 200, 'message' => '创建成功'], $repository->records[0]['result']);
        $this->assertArrayHasKey('operation_time', $repository->records[0]);
    }

    public function test_a_failing_fallback_write_never_changes_the_response(): void
    {
        RequestContext::setActingUser(7);

        $response = (new AdminLogMiddleware(new SpyOperationLogRepository(true), new SpyQueueDispatcher(true)))->process(
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
        $dispatcher = new SpyQueueDispatcher();

        (new AdminLogMiddleware(new SpyOperationLogRepository(), $dispatcher))->process($this->request('POST'), static fn () => new Response(204, [], ''));

        $this->assertSame(['code' => 204, 'message' => ''], $this->onlyPayload($dispatcher)['result']);
    }

    public function test_short_key_strips_the_namespace(): void
    {
        $this->assertSame('AdminController@store', AdminLogMiddleware::shortKey('app\\adminapi\\controller\\system\\AdminController', 'store'));
        $this->assertSame('Bare@run', AdminLogMiddleware::shortKey('Bare', 'run'));
    }
}
