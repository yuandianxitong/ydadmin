<?php

declare(strict_types=1);

namespace app\service\wechat;

use core\contract\ConfigValueReader;
use core\wechat\exception\WechatNotConfiguredException;
use DI\Attribute\Inject;
use support\Response;

/**
 * 公众号 openid 的绑定证明（M6a spec §5）。
 *
 * wechat-h5-login 在「openid 未绑定任何账号」时下发；bind-oa-openid 只认这张 cookie 里的 openid，不认请求体——
 * 1.x 直接把客户端传来的 oa_openid 写进用户行，知道别人 openid 的人就能把它绑到自己账号上，收对方的消息、
 * 冒用对方身份做 JSAPI 支付。HttpOnly 让页面脚本读不到，HMAC 让知道 openid 也伪造不出来。
 *
 * 值：base64url(openid) . issued_at . base64url(hmac)，用点分隔，只含 URL 安全字符（workerman 下发时 rawurlencode，
 * 回传时 Request::cookie() 不做解码，两边字节一致）。
 * 密钥：从用户 JWT 密钥派生，派生标签隔离用途——即使同一密钥也签不出 JWT 能接受的东西，反之亦然。
 *
 * 容器单例，无实例态。
 */
class WechatOaBindCookie
{
    public const NAME = 'yd_oa_bind';

    private const PATH = '/api';

    private const MAX_OPENID_BYTES = 128;

    private const MAX_VALUE_BYTES = 512;

    /** 多台服务器之间的时钟偏差容忍度（秒）：签发时间在未来超过它视为无效 */
    private const CLOCK_SKEW = 60;

    #[Inject]
    protected ConfigValueReader $config;

    /** @throws WechatNotConfiguredException 用户 JWT 密钥为空（不得用空 key 签名） */
    public function issue(string $openid, ?int $now = null): string
    {
        if ($openid === '' || strlen($openid) > self::MAX_OPENID_BYTES) {
            throw new \InvalidArgumentException('openid 为空或过长');
        }
        $issuedAt = (string) ($now ?? time());

        return self::encode($openid) . '.' . $issuedAt . '.' . self::encode($this->mac($openid, $issuedAt));
    }

    /** 返回 openid；空值、格式错、篡改、过期、密钥为空一律返回 null（不抛出，调用方统一按「授权已失效」处理） */
    public function verify(?string $value, ?int $now = null): ?string
    {
        if ($value === null || $value === '' || strlen($value) > self::MAX_VALUE_BYTES) {
            return null;
        }
        $parts = explode('.', $value);
        if (count($parts) !== 3 || $parts[1] === '' || strlen($parts[1]) > 12 || !ctype_digit($parts[1])) {
            return null;
        }
        $openid = self::decode($parts[0]);
        $mac = self::decode($parts[2]);
        if ($openid === null || $openid === '' || strlen($openid) > self::MAX_OPENID_BYTES || $mac === null) {
            return null;
        }

        try {
            $expected = $this->mac($openid, $parts[1]);
        } catch (WechatNotConfiguredException) {
            return null;
        }
        if (!hash_equals($expected, $mac)) {
            return null;
        }

        $age = ($now ?? time()) - (int) $parts[1];
        if ($age < -self::CLOCK_SKEW || $age > $this->ttl()) {
            return null;
        }

        return $openid;
    }

    public function attach(Response $response, string $openid): Response
    {
        $response->cookie(self::NAME, $this->issue($openid), $this->ttl(), self::PATH, '', $this->secure(), true, 'Lax');

        return $response;
    }

    public function forget(Response $response): Response
    {
        $response->cookie(self::NAME, '', 0, self::PATH, '', $this->secure(), true, 'Lax');

        return $response;
    }

    private function mac(string $openid, string $issuedAt): string
    {
        $secret = (string) config('auth.jwt.user.key', '');
        if ($secret === '') {
            throw new WechatNotConfiguredException('用户令牌密钥未配置，无法签发公众号绑定凭证');
        }
        $key = hash_hmac('sha256', 'wechat-oa-bind', $secret, true);

        return hash_hmac('sha256', $openid . '|' . $issuedAt, $key, true);
    }

    private function ttl(): int
    {
        return max(60, (int) config('wechat.oa_bind_cookie_ttl', 604800));
    }

    /** site_url 是 https 时加 Secure；http（本地开发）不加，否则浏览器根本不回传 */
    private function secure(): bool
    {
        return str_starts_with(strtolower(trim((string) $this->config->getConfigValue('site_url', ''))), 'https://');
    }

    private static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private static function decode(string $text): ?string
    {
        if ($text === '' || preg_match('/^[A-Za-z0-9_-]+$/D', $text) !== 1) {
            return null;
        }
        $padded = strtr($text, '-_', '+/') . str_repeat('=', (4 - strlen($text) % 4) % 4);
        $bytes = base64_decode($padded, true);

        return $bytes === false ? null : $bytes;
    }
}
