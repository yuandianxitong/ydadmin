<?php

declare(strict_types=1);

namespace core\realtime;

/**
 * WS 进程内的「管理员 → 连接」表（spec §5）。由 WebSocketServer 实例持有——进程实例状态，不是静态属性。
 * 连接对象约定有 public int $id（Workerman\Connection\TcpConnection::$id 即是）；同一连接只能属于一个管理员。
 */
final class ConnectionRegistry
{
    /** @var array<int, array<int, object>> adminId → [connectionId → connection] */
    private array $byAdmin = [];

    /** @var array<int, int> connectionId → adminId */
    private array $owner = [];

    public function add(int $adminId, object $connection): void
    {
        $this->remove($connection);
        $id = self::idOf($connection);
        $this->byAdmin[$adminId][$id] = $connection;
        $this->owner[$id] = $adminId;
    }

    /** @return int|null 原所属管理员 id；连接未登记时返回 null */
    public function remove(object $connection): ?int
    {
        $id = self::idOf($connection);
        if (!isset($this->owner[$id])) {
            return null;
        }
        $adminId = $this->owner[$id];
        unset($this->owner[$id], $this->byAdmin[$adminId][$id]);
        if (($this->byAdmin[$adminId] ?? []) === []) {
            unset($this->byAdmin[$adminId]);
        }

        return $adminId;
    }

    public function adminOf(object $connection): ?int
    {
        return $this->owner[self::idOf($connection)] ?? null;
    }

    /**
     * @param 'all'|list<int> $targets
     * @return list<object>
     */
    public function forTargets(string|array $targets): array
    {
        if ($targets === 'all') {
            return $this->all();
        }
        $connections = [];
        foreach ((array) $targets as $adminId) {
            foreach ($this->byAdmin[(int) $adminId] ?? [] as $connection) {
                $connections[] = $connection;
            }
        }

        return $connections;
    }

    /** @return list<int> */
    public function adminIds(): array
    {
        return array_keys($this->byAdmin);
    }

    /** @return list<object> */
    public function all(): array
    {
        $connections = [];
        foreach ($this->byAdmin as $group) {
            foreach ($group as $connection) {
                $connections[] = $connection;
            }
        }

        return $connections;
    }

    public function count(): int
    {
        return count($this->owner);
    }

    private static function idOf(object $connection): int
    {
        return property_exists($connection, 'id') ? (int) $connection->id : spl_object_id($connection);
    }
}
