<?php

declare(strict_types=1);

namespace app\service\realtime;

use core\base\Service;
use core\exception\BusinessException;
use support\Redis;

/**
 * WebSocket 握手票据（spec §3 决策 3、§6）：浏览器的 WebSocket 不能带 Authorization 头，JWT 也不该进 URL，
 * 所以前端先带 JWT 调 HTTP 换一张 30 秒、一次性的随机票据，再用它握手。
 *
 * 票据载荷 {admin_id, ver, jti, ip, ua}：ver / jti 取自签发时的 token，握手与复查时据此判断会话是否已被吊销；
 * ip 由 HTTP 侧经 ClientIp 取得（WS 握手拿到的是 Workerman 原生 Request，不能传给 ClientIp）。
 * consume() 用 Lua 原子 GET + DEL（等同 GETDEL，但 support\Redis 门面没有 getDel 的类型声明，且不抬高 Redis 版本下限）。
 * 容器单例，无状态。
 */
class WsTicketService extends Service
{
    public const TTL = 30;

    /** 同一管理员 RATE_WINDOW 秒内最多签发 RATE_LIMIT 张：前端重连逻辑出错时不至于把接口刷爆。 */
    public const RATE_LIMIT = 30;

    public const RATE_WINDOW = 60;

    private const KEY_PREFIX = 'ws:ticket:';

    private const PATTERN = '/^[0-9a-f]{48}$/';

    private const GET_AND_DELETE = "local v = redis.call('GET', KEYS[1]) if v then redis.call('DEL', KEYS[1]) end return v";

    /** 计数 +1 并保证带过期时间，一段 Lua 原子执行（与 LoginRateLimitMiddleware 同一写法）。 */
    private const INCR_WITH_TTL = "local n = redis.call('INCR', KEYS[1]) if n == 1 or redis.call('TTL', KEYS[1]) == -1 then redis.call('EXPIRE', KEYS[1], ARGV[1]) end return n";

    /**
     * 限流后签发（控制器入口）。
     *
     * @return array{ticket: string, expires_in: int}
     * @throws BusinessException code 429：超出频率
     */
    public function grant(int $adminId, int $ver, string $jti, string $ip, string $ua): array
    {
        $count = (int) Redis::eval(self::INCR_WITH_TTL, 1, "ws:ticket:rate:{$adminId}", self::RATE_WINDOW);
        if ($count > self::RATE_LIMIT) {
            throw new BusinessException(lang('business.ws_ticket_rate_limited'), 429);
        }

        return ['ticket' => $this->issue($adminId, $ver, $jti, $ip, $ua), 'expires_in' => self::TTL];
    }

    public function issue(int $adminId, int $ver, string $jti, string $ip, string $ua): string
    {
        $ticket = bin2hex(random_bytes(24));
        $payload = json_encode([
            'admin_id' => $adminId,
            'ver'      => $ver,
            'jti'      => $jti,
            'ip'       => $ip,
            'ua'       => mb_substr(mb_scrub($ua, 'UTF-8'), 0, 255),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        Redis::set(self::KEY_PREFIX . $ticket, $payload, 'EX', self::TTL);

        return $ticket;
    }

    /** @return array{admin_id: int, ver: int, jti: string, ip: string, ua: string}|null 格式非法、不存在、已用过或已过期时返回 null */
    public function consume(string $ticket): ?array
    {
        if (preg_match(self::PATTERN, $ticket) !== 1) {
            return null;
        }
        $raw = Redis::eval(self::GET_AND_DELETE, 1, self::KEY_PREFIX . $ticket);
        if (!is_string($raw)) {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data)
            || !is_int($data['admin_id'] ?? null) || $data['admin_id'] <= 0
            || !is_int($data['ver'] ?? null)
            || !is_string($data['jti'] ?? null) || $data['jti'] === ''
            || !is_string($data['ip'] ?? null)
            || !is_string($data['ua'] ?? null)) {
            return null;
        }

        return ['admin_id' => $data['admin_id'], 'ver' => $data['ver'], 'jti' => $data['jti'], 'ip' => $data['ip'], 'ua' => $data['ua']];
    }
}
