<?php

declare(strict_types=1);

namespace app\middleware;

use app\repository\system\AdminOperationLogRepository;
use core\context\RequestContext;
use core\helper\SensitiveDataMasker;
use core\http\ClientIp;
use support\Log;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 操作日志（spec §6.2，移植 Saas，去租户）。挂在认证组最内层：AdminAuth → AdminPermission → AdminLog。
 *
 * - 只记 POST/PUT/DELETE，且有管理员身份（只认 RequestContext::actingUser()，与权限中间件一致）。
 * - 同步写库：TP8 入队异步写，没有队列消费者就永不落库。写失败只记 Log::warning，绝不影响响应。
 * - params 经 SensitiveDataMasker 脱敏，再按 admin_log.masked_params 整字段脱敏；result 取响应体的 {code, message}。
 * - 动作文案按「短类名@方法」查 config/admin_log.php（值是 lang 键，按当次请求 locale 解析），带 /{id} 的路径同样命中；
 *   未收录的落到 messages.operation / messages.execute_operation。
 *
 * 容器单例：计时起点是 process() 的局部变量；除构造注入的仓储外没有任何实例属性，不缓存 $request/$response。
 */
class AdminLogMiddleware implements MiddlewareInterface
{
    private const WRITE_METHODS = ['POST', 'PUT', 'DELETE'];

    public function __construct(private readonly AdminOperationLogRepository $repository)
    {
    }

    public function process(Request $request, callable $handler): Response
    {
        $start = microtime(true);
        $response = $handler($request);

        $method = strtoupper($request->method());
        $adminId = RequestContext::actingUser();
        if ($adminId <= 0 || !in_array($method, self::WRITE_METHODS, true)) {
            return $response;
        }

        try {
            $this->write($request, $response, $method, $adminId, round(microtime(true) - $start, 3));
        } catch (\Throwable $e) {
            Log::warning('写操作日志失败：' . $e->getMessage(), ['path' => $request->path()]);
        }

        return $response;
    }

    /** 「短类名@方法」：app\adminapi\controller\system\AdminController + store → AdminController@store。 */
    public static function shortKey(string $controller, string $action): string
    {
        $pos = strrpos($controller, '\\');

        return ($pos === false ? $controller : substr($controller, $pos + 1)) . '@' . $action;
    }

    private function write(Request $request, Response $response, string $method, int $adminId, float $executionTime): void
    {
        $key = self::shortKey(
            is_string($request->controller) ? $request->controller : '',
            is_string($request->action) ? $request->action : ''
        );
        [$action, $description] = self::describe($key);
        [$code, $message] = self::parseResult($response);

        $this->repository->record([
            'admin_id'       => $adminId,
            'username'       => (string) ($request->username ?? ''),
            'method'         => $method,
            'path'           => $request->path(),
            'ip'             => ClientIp::resolve($request),
            'user_agent'     => (string) $request->header('user-agent', ''),
            'action'         => $action,
            'description'    => $description,
            'params'         => self::params($request, $key),
            'result'         => ['code' => $code, 'message' => $message],
            'execution_time' => $executionTime,
        ]);
    }

    /** @return array{0: string, 1: string} [action, description] */
    private static function describe(string $key): array
    {
        $pair = ((array) config('admin_log.actions', []))[$key] ?? null;
        if (!is_array($pair) || count($pair) !== 2) {
            return [lang('messages.operation'), lang('messages.execute_operation')];
        }
        [$action, $description] = array_values($pair);

        return [lang((string) $action), lang((string) $description)];
    }

    /** @return array<array-key, mixed> */
    private static function params(Request $request, string $key): array
    {
        $params = SensitiveDataMasker::mask((array) $request->all());
        $fields = ((array) config('admin_log.masked_params', []))[$key] ?? [];
        foreach ((array) $fields as $field) {
            if (is_string($field) && array_key_exists($field, $params)) {
                $params[$field] = SensitiveDataMasker::MASK;
            }
        }

        return $params;
    }

    /**
     * 响应体是统一信封时取 {code, message}；不是 JSON（文件流等）时 code 取 HTTP 状态、message 为空。
     *
     * @return array{0: int, 1: string}
     */
    private static function parseResult(Response $response): array
    {
        $body = json_decode((string) $response->rawBody(), true);
        if (!is_array($body)) {
            return [$response->getStatusCode(), ''];
        }

        return [
            isset($body['code']) ? (int) $body['code'] : $response->getStatusCode(),
            isset($body['message']) ? (string) $body['message'] : '',
        ];
    }
}
