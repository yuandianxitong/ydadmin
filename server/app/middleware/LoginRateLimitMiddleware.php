<?php

declare(strict_types=1);

namespace app\middleware;

use app\service\system\SystemConfigService;
use core\response\Api;
use support\Redis;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 登录限流（spec §4.3，只挂在 auth/login）：按 md5(IP|用户名) 计数，以响应体 code !== 200 计一次失败
 * （TP8 按 HTTP 状态判断，业务失败也是 200，几乎永不锁定）；达到 login_max_retry 次锁定
 * login_lock_duration 分钟，锁定期间 HTTP 200 + code 429；成功清零。计数用 Redis INCR + EXPIRE。
 * 容器单例，不存请求态。
 */
class LoginRateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly SystemConfigService $configs)
    {
    }

    public function process(Request $request, callable $handler): Response
    {
        $username = $request->post('username');
        $hash = md5($request->getRealIp() . '|' . (is_string($username) ? $username : ''));
        $lockKey = "login_lock:{$hash}";
        $failKey = "login_fail:{$hash}";

        $remaining = (int) Redis::ttl($lockKey);
        if ($remaining > 0) {
            return Api::error(sprintf(lang('messages.login_rate_limit'), $remaining), 429);
        }

        /** @var Response $response */
        $response = $handler($request);
        $body = json_decode((string) $response->rawBody(), true);
        if (is_array($body) && (int) ($body['code'] ?? 0) === 200) {
            Redis::del($failKey);

            return $response;
        }

        $maxRetry = max(1, (int) $this->configs->getConfigValue('login_max_retry', 5));
        $lockSeconds = max(1, (int) $this->configs->getConfigValue('login_lock_duration', 30)) * 60;
        $fails = (int) Redis::incr($failKey);
        if ($fails === 1) {
            Redis::expire($failKey, $lockSeconds);
        }
        if ($fails >= $maxRetry) {
            Redis::setEx($lockKey, $lockSeconds, '1');
            Redis::del($failKey);
        }

        return $response;
    }
}
