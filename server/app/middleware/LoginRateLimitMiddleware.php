<?php

declare(strict_types=1);

namespace app\middleware;

use app\service\system\SystemConfigService;
use core\http\ClientIp;
use core\response\Api;
use support\Redis;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 登录限流（spec §4.3）：挂在 /adminapi/auth/login 与 /api/auth/login（M5a 起 C 端复用同一套，见 account()）。
 * 按 md5(IP|账号) 计数（IP 取 core\http\ClientIp：只有直连地址是
 * 可信代理时才读 X-Forwarded-For，否则伪造该头就能每次换一个 key 绕过锁定），以响应体 code !== 200 计一次失败
 * （TP8 按 HTTP 状态判断，业务失败也是 200，几乎永不锁定）；达到 login_max_retry 次锁定
 * login_lock_duration 分钟，锁定期间 HTTP 200 + code 429；成功清零。计数的 INCR 与 EXPIRE 在一段 Lua 里原子执行。
 * 用户名统一 trim + mb_strtolower 后再算 key：admins.username 是不区分大小写/重音的排序规则
 * （utf8mb4_0900_ai_ci），大小写/重音变体会登录同一个账号，必须落在同一个限流 key 上，否则限流可被绕过。
 * 容器单例，不存请求态。
 */
class LoginRateLimitMiddleware implements MiddlewareInterface
{
    /**
     * 失败计数 +1 并保证计数带过期时间，一段 Lua 原子执行：INCR 与 EXPIRE 分成两条命令时，worker 在两条之间
     * 退出会留下不过期的计数；遇到 TTL 为 -1 的计数（存在却不过期，旧版可能留下）顺带补上过期时间。
     */
    private const INCR_WITH_TTL = "local n = redis.call('INCR', KEYS[1]) if n == 1 or redis.call('TTL', KEYS[1]) == -1 then redis.call('EXPIRE', KEYS[1], ARGV[1]) end return n";

    public function __construct(private readonly SystemConfigService $configs)
    {
    }

    public function process(Request $request, callable $handler): Response
    {
        $account = $this->account($request);
        $hash = md5(ClientIp::resolve($request) . '|' . (is_string($account) ? mb_strtolower(trim($account)) : ''));
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
        $fails = (int) Redis::eval(self::INCR_WITH_TTL, 1, $failKey, $lockSeconds);
        if ($fails >= $maxRetry) {
            Redis::setEx($lockKey, $lockSeconds, '1');
            Redis::del($failKey);
        }

        return $response;
    }

    /**
     * 与 core\base\Controller::body() 取同一个请求体来源：post() 非空就用它，否则兜底解析 JSON
     * 请求体（中间件不是 Controller，拿不到基类的 body()，这里单独实现一份同样的逻辑）。
     *
     * 账号字段名按 username → account → mobile 依次回退：管理端登录发 username，C 端 pc 发 account、
     * uniapp 发 mobile（M5a 把这个中间件复用到 /api/auth/login，最终评审第 2 条）。对管理端是纯追加——
     * username 在就还是取它，行为一个字节都不变；C 端两个字段名也各自落在「IP + 账号」的精确 key 上，
     * 而不是取不到账号、退化成按 IP 一刀切：后者会让同一个出口 NAT 后的正常会员互相锁死。
     */
    private function account(Request $request): mixed
    {
        $data = $request->post();
        if (!is_array($data) || $data === []) {
            $json = json_decode((string) $request->rawBody(), true);
            $data = is_array($json) ? $json : [];
        }

        return $data['username'] ?? $data['account'] ?? $data['mobile'] ?? null;
    }
}
