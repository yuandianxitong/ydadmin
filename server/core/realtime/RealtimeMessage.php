<?php

declare(strict_types=1);

namespace core\realtime;

/**
 * 实时通道的一条消息（spec §5、§6）：业务进程经 RealtimePublisher 发布到 Redis 频道，WS 进程订阅后按 targets 投递。
 *
 * targets 为 'all' 或管理员 id 列表（去重、正整数）；event 只允许 EVENTS 里的业务事件——connected / pong
 * 由 WS 进程自己下发，不经 Redis。下发给浏览器的只有 frame()，targets 不外泄。
 * 值对象，无状态。
 */
final class RealtimeMessage
{
    public const EVENTS = ['notification.created', 'force_logout', 'task.progress'];

    /** @var 'all'|list<int> */
    public readonly string|array $targets;

    public readonly string $id;

    public readonly int $ts;

    /**
     * @param 'all'|list<int>|string|array<array-key, mixed> $targets
     * @param array<string, mixed> $payload
     * @throws \InvalidArgumentException event 不在白名单或 targets 非法
     */
    public function __construct(
        string|array $targets,
        public readonly string $event,
        public readonly array $payload,
        string $id = '',
        int $ts = 0,
    ) {
        if (!in_array($event, self::EVENTS, true)) {
            throw new \InvalidArgumentException("未知的实时事件：{$event}");
        }
        $this->targets = self::normaliseTargets($targets);
        $this->id = $id !== '' ? $id : bin2hex(random_bytes(8));
        $this->ts = $ts > 0 ? $ts : time();
    }

    /** @throws \RuntimeException 载荷无法编码为 JSON（如非法 UTF-8） */
    public function encode(): string
    {
        try {
            return json_encode([
                'targets' => $this->targets,
                'event'   => $this->event,
                'payload' => $this->payload,
                'id'      => $this->id,
                'ts'      => $this->ts,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (\JsonException $e) {
            throw new \RuntimeException('实时消息编码失败：' . $e->getMessage(), 0, $e);
        }
    }

    public static function decode(string $raw): ?self
    {
        try {
            $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        if (!is_array($data)
            || !isset($data['targets'], $data['event'], $data['payload'], $data['id'], $data['ts'])
            || !(is_string($data['targets']) || is_array($data['targets']))
            || !is_string($data['event'])
            || !is_array($data['payload'])
            || !is_string($data['id'])
            || !is_int($data['ts'])) {
            return null;
        }

        try {
            /** @var array<string, mixed> $payload */
            $payload = $data['payload'];

            return new self($data['targets'], $data['event'], $payload, $data['id'], $data['ts']);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    public function targetsAdmin(int $adminId): bool
    {
        return $this->targets === 'all' || in_array($adminId, $this->targets, true);
    }

    /** @return array{event: string, payload: array<string, mixed>, id: string, ts: int} */
    public function frame(): array
    {
        return ['event' => $this->event, 'payload' => $this->payload, 'id' => $this->id, 'ts' => $this->ts];
    }

    /**
     * @param string|array<array-key, mixed> $targets
     * @return 'all'|list<int>
     */
    private static function normaliseTargets(string|array $targets): string|array
    {
        if (is_string($targets)) {
            if ($targets !== 'all') {
                throw new \InvalidArgumentException("非法的推送目标：{$targets}");
            }

            return 'all';
        }
        if ($targets === []) {
            throw new \InvalidArgumentException('推送目标不能为空');
        }
        foreach ($targets as $id) {
            if (!is_int($id) || $id <= 0) {
                throw new \InvalidArgumentException('推送目标必须是正整数管理员 id');
            }
        }

        return array_values(array_unique($targets));
    }
}
