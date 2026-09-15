<?php

declare(strict_types=1);

namespace app\service\realtime;

use core\base\Service;
use support\Redis;

/**
 * 管理员在线状态（spec §5、§7「心跳与在线一致性」），全部存 Redis，多进程 / 多实例共享：
 *
 * - hash ws:online:{adminId}：字段 {主机名}:{pid}:{连接号} → JSON {ip, ua, connected_at}；每次 join / touch 续期 90 秒，
 *   进程崩溃来不及 leave 时由 TTL 自然清理。
 * - zset ws:online:index：member = adminId，score = 最后心跳秒；列表查询时顺手剔除 hash 已过期的成员。
 *
 * 容器单例，无状态。
 */
class PresenceService extends Service
{
    public const TTL = 90;

    public const INDEX_KEY = 'ws:online:index';

    private const KEY_PREFIX = 'ws:online:';

    /** @param array{ip: string, ua: string, connected_at: string} $meta */
    public function join(int $adminId, string $field, array $meta): void
    {
        $key = self::key($adminId);
        Redis::hSet($key, $field, (string) json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
        Redis::expire($key, self::TTL);
        Redis::zAdd(self::INDEX_KEY, time(), (string) $adminId);
    }

    /** 心跳续期。管理员已不在线（hash 已过期或已清）时什么都不做，不会凭空建出键。 */
    public function touch(int $adminId): void
    {
        $key = self::key($adminId);
        if ((int) Redis::exists($key) === 0) {
            return;
        }
        Redis::expire($key, self::TTL);
        Redis::zAdd(self::INDEX_KEY, time(), (string) $adminId);
    }

    public function leave(int $adminId, string $field): void
    {
        $key = self::key($adminId);
        Redis::hDel($key, $field);
        if ((int) Redis::hLen($key) === 0) {
            Redis::del($key);
            Redis::zRem(self::INDEX_KEY, (string) $adminId);
        }
    }

    /** @return int 清除前的连接字段数 */
    public function forget(int $adminId): int
    {
        $key = self::key($adminId);
        $count = (int) Redis::hLen($key);
        Redis::del($key);
        Redis::zRem(self::INDEX_KEY, (string) $adminId);

        return $count;
    }

    /** @return list<int> 仍在线的管理员，按最后心跳倒序；hash 已过期的 zset 成员被顺手移除 */
    public function onlineAdminIds(): array
    {
        $online = [];
        foreach ((array) Redis::zRevRange(self::INDEX_KEY, 0, -1) as $member) {
            $adminId = (int) $member;
            if ($adminId <= 0 || (int) Redis::exists(self::key($adminId)) === 0) {
                Redis::zRem(self::INDEX_KEY, (string) $member);

                continue;
            }
            $online[] = $adminId;
        }

        return $online;
    }

    /** @return array{connections: int, ip: string, ua: string, connected_at: string, last_seen: string}|null ip / ua / connected_at 取最近建立的连接 */
    public function describe(int $adminId): ?array
    {
        $fields = (array) Redis::hGetAll(self::key($adminId));
        if ($fields === []) {
            return null;
        }
        $latest = ['ip' => '', 'ua' => '', 'connected_at' => ''];
        foreach ($fields as $value) {
            $meta = json_decode((string) $value, true);
            if (!is_array($meta)) {
                continue;
            }
            $connectedAt = (string) ($meta['connected_at'] ?? '');
            if ($connectedAt >= $latest['connected_at']) {
                $latest = ['ip' => (string) ($meta['ip'] ?? ''), 'ua' => (string) ($meta['ua'] ?? ''), 'connected_at' => $connectedAt];
            }
        }
        $score = Redis::zScore(self::INDEX_KEY, (string) $adminId);

        return [
            'connections'  => count($fields),
            'ip'           => $latest['ip'],
            'ua'           => $latest['ua'],
            'connected_at' => $latest['connected_at'],
            'last_seen'    => date('Y-m-d H:i:s', is_numeric($score) ? (int) $score : time()),
        ];
    }

    private static function key(int $adminId): string
    {
        return self::KEY_PREFIX . $adminId;
    }
}
