<?php

declare(strict_types=1);

namespace core\http;

use Webman\Http\Request;

/**
 * 客户端 IP（登录限流 key、登录日志、last_login_ip 共用）。
 *
 * webman 的 getRealIp() 在直连地址是私网/回环时信任 X-Forwarded-For 的第一项——那一项由客户端任意填写，
 * 伪造它就能绕过登录锁定、伪造登录日志。这里只在直连地址属于 TRUSTED_PROXIES 时才读 X-Forwarded-For，
 * 并从右往左跳过可信代理，取第一个其余地址（最右侧由我们自己的代理追加，客户端伪造不了）；
 * 该地址不是合法 IP 时直接退回直连地址，不再往左读（更左边的都是客户端可控的）。X-Real-IP 等其它头一律不读。
 * 无状态。
 */
final class ClientIp
{
    /** @param list<string>|null $trusted 可信代理 IP；默认读 config('proxy.trusted_proxies')，测试可直接传入 */
    public static function resolve(Request $request, ?array $trusted = null): string
    {
        $trusted ??= array_values(array_map('strval', (array) config('proxy.trusted_proxies', [])));
        $remote = $request->getRemoteIp();
        if (!in_array($remote, $trusted, true)) {
            return $remote;
        }

        $header = $request->header('x-forwarded-for');
        $entries = is_string($header) ? array_filter(array_map('trim', explode(',', $header)), static fn (string $ip): bool => $ip !== '') : [];
        foreach (array_reverse($entries) as $ip) {
            if (in_array($ip, $trusted, true)) {
                continue;
            }

            return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : $remote;
        }

        return $remote;
    }
}
