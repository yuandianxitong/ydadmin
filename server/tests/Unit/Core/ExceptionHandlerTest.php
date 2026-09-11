<?php

declare(strict_types=1);

namespace tests\Unit\Core;

use app\exception\Handler;
use core\exception\AuthException;
use core\exception\BusinessException;
use core\exception\ForbiddenException;
use core\exception\NotFoundException;
use core\exception\ValidationException;
use Psr\Log\NullLogger;
use tests\TestCase;
use Webman\Http\Request;
use Webman\Http\Response;

final class ExceptionHandlerTest extends TestCase
{
    private function render(\Throwable $e, bool $debug = false): Response
    {
        $handler = new Handler(new NullLogger(), $debug);
        $request = new Request("GET /adminapi/health HTTP/1.1\r\nHost: localhost\r\n\r\n");
        return $handler->render($request, $e);
    }

    /** @return array<string, mixed> */
    private function body(Response $response): array
    {
        return json_decode((string) $response->rawBody(), true);
    }

    public function test_business_exception_defaults_to_400_with_http_200(): void
    {
        $response = $this->render(new BusinessException('操作失败'));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(400, $this->body($response)['code']);
        $this->assertSame('操作失败', $this->body($response)['message']);
    }

    public function test_business_exception_custom_code(): void
    {
        $this->assertSame(409, $this->body($this->render(new BusinessException('冲突', 409)))['code']);
    }

    public function test_business_exception_5xx_code_sets_http_status(): void
    {
        $response = $this->render(new BusinessException('维护中', 503));
        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame(503, $this->body($response)['code']);
    }

    public function test_auth_and_forbidden(): void
    {
        // 前端最依赖的约定：401/403 只写在 body.code，HTTP 状态仍是 200
        $auth = $this->render(new AuthException());
        $this->assertSame(200, $auth->getStatusCode());
        $this->assertSame(401, $this->body($auth)['code']);

        $forbidden = $this->render(new ForbiddenException());
        $this->assertSame(200, $forbidden->getStatusCode());
        $this->assertSame(403, $this->body($forbidden)['code']);
    }

    public function test_not_found_exception_is_business_404_with_http_200(): void
    {
        $response = $this->render(new NotFoundException());

        $this->assertSame(200, $response->getStatusCode(), '记录不存在是业务错误，区别于未知路由的 HTTP 404');
        $this->assertSame(404, $this->body($response)['code']);
        $this->assertSame(lang('messages.data_not_found'), $this->body($response)['message']);
    }

    public function test_validation_exception_renders_422_with_errors(): void
    {
        $errors = ['name' => '名称不能为空', 'age' => '年龄必须是整数'];
        $body = $this->body($this->render(new ValidationException($errors)));

        $this->assertSame(422, $body['code']);
        $this->assertSame('名称不能为空', $body['message']);
        $this->assertSame(['errors' => $errors], $body['data']);
    }

    public function test_unknown_exception_hides_details_without_debug(): void
    {
        $response = $this->render(new \RuntimeException('SQLSTATE secret'));
        $body = $this->body($response);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(500, $body['code']);
        $this->assertSame('服务器内部错误', $body['message']);
        $this->assertStringNotContainsString('SQLSTATE', (string) $response->rawBody());
        $this->assertStringNotContainsString('.php', (string) $response->rawBody());
    }

    public function test_unknown_exception_shows_details_in_debug(): void
    {
        $body = $this->body($this->render(new \RuntimeException('boom'), true));

        $this->assertSame('boom', $body['message']);
        $this->assertSame(\RuntimeException::class, $body['data']['exception']);
        $this->assertSame(__FILE__, $body['data']['file']);
        $this->assertIsInt($body['data']['line']);
    }
}
