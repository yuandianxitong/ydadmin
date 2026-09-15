<?php

declare(strict_types=1);

namespace app\process;

use app\service\realtime\PresenceService;
use app\service\realtime\WsTicketService;
use core\auth\TokenManager;
use core\auth\TokenVersion;
use core\realtime\ConnectionRegistry;
use core\realtime\RealtimeMessage;
use core\realtime\RealtimePublisher;
use support\Container;
use support\Context;
use support\Log;
use Workerman\Redis\Client as AsyncRedis;
use Workerman\Timer;
use Workerman\Worker;

/**
 * 管理员 WebSocket 实时通道进程（spec §5、§7），config/process.php 注册为 websocket。
 *
 * Workerman 入口（onWebSocketConnect / onMessage / onClose / 订阅回调 / 定时器）只做 try/catch 转接，
 * 业务都在可测的公开方法里。vendor 在 onWebSocketConnect / onWebSocketConnected 内遇到未捕获异常会
 * Worker::stopAll()（Protocols/Websocket.php），所以每个入口都吞掉 \Throwable 并记日志。
 *
 * 握手分两步：onWebSocketConnect 在握手响应发出之前被调用，此时发不了 WebSocket 帧，只做校验并给这个
 * 连接挂上 onWebSocketConnected（webman 的 worker_bind 不绑定该回调，而 vendor 优先读连接上的属性）；
 * 握手完成后 afterHandshake() 才下发 connected 或带关闭码关闭。
 *
 * 进程内状态（连接表、连接元数据、异步订阅客户端）都是实例属性，不是静态属性。
 * 订阅连接只做订阅：TokenVersion、黑名单、在线状态一律走同步 Redis（设计决定 3）。
 * 异步客户端断线后由 workerman/redis 自己重连并重发 SUBSCRIBE（库内固定 5 秒），不另写重连定时器。
 */
class WebSocketServer
{
    public const CLOSE_HEARTBEAT = 4000;
    public const CLOSE_TICKET = 4001;
    public const CLOSE_REVOKED = 4003;
    public const HEARTBEAT_TIMEOUT = 90;

    /** 客户端 ping 间隔（秒），随 connected 下发。 */
    private const HEARTBEAT_INTERVAL = 25;

    private const SWEEP_INTERVAL = 30;

    private const RECHECK_INTERVAL = 60;

    /** RFC 6455：控制帧 payload ≤ 125 字节，扣掉 2 字节关闭码。 */
    private const MAX_REASON_BYTES = 123;

    private readonly ConnectionRegistry $registry;

    private readonly \Closure $versionOf;

    private readonly \Closure $isRevoked;

    /**
     * 连接 id → 元数据。握手通过：{admin_id, ver, jti, field, last_ping}；握手被拒：{reject_code, reject_reason}。
     *
     * @var array<int, array<string, int|string>>
     */
    private array $meta = [];

    private ?AsyncRedis $subscriber = null;

    /**
     * 构造参数仅供测试注入。生产环境下 webman 按 config/process.php 构造进程类（未配置 constructor），
     * 全部为 null，按需从容器取服务、用 TokenVersion / TokenManager 做吊销判定。
     *
     * @param (\Closure(int): int)|null     $versionOf 管理员 → 当前 token 版本号
     * @param (\Closure(string): bool)|null $isRevoked jti 是否已拉黑
     */
    public function __construct(
        private readonly ?WsTicketService $tickets = null,
        private readonly ?PresenceService $presence = null,
        ?ConnectionRegistry $registry = null,
        ?\Closure $versionOf = null,
        ?\Closure $isRevoked = null,
    ) {
        $this->registry = $registry ?? new ConnectionRegistry();
        $this->versionOf = $versionOf ?? static fn (int $adminId): int => TokenVersion::current($adminId);
        $this->isRevoked = $isRevoked ?? static fn (string $jti): bool => TokenManager::scope('admin')->isJtiRevoked($jti);
    }

    // ------------------------------------------------------------------ Workerman 入口

    public function onWorkerStart(Worker $worker): void
    {
        $this->guard('ws.worker_start', function (): void {
            $this->subscribe();
        });
        Timer::add(self::SWEEP_INTERVAL, function (): void {
            $this->guard('ws.sweep', fn () => $this->sweep(time()));
        });
        Timer::add(self::RECHECK_INTERVAL, function (): void {
            $this->guard('ws.recheck', fn () => $this->recheckRevocation());
        });
    }

    public function onWebSocketConnect(object $connection, object $request): void
    {
        $this->guard('ws.handshake', function () use ($connection, $request): void {
            try {
                $this->handleHandshake($connection, $request);
            } catch (\Throwable $e) {
                // 校验依赖（Redis 等）出错时 fail closed：按票据无效拒绝
                $this->meta[(int) $connection->id] = ['reject_code' => self::CLOSE_TICKET, 'reject_reason' => 'handshake_error'];
                throw $e;
            } finally {
                $connection->onWebSocketConnected = function (object $connected): void {
                    $this->guard('ws.after_handshake', fn () => $this->afterHandshake($connected));
                };
            }
        });
    }

    public function onMessage(object $connection, mixed $data): void
    {
        $this->guard('ws.message', fn () => $this->handleMessage($connection, (string) $data));
    }

    public function onClose(object $connection): void
    {
        $this->guard('ws.close', fn () => $this->handleClose($connection));
    }

    // ------------------------------------------------------------------ 可测的公开方法

    /** 握手响应发出之前：一次性票据 → 版本号 → jti 黑名单；通过则登记，失败只记下关闭码（此时发不了帧）。 */
    public function handleHandshake(object $connection, object $request): void
    {
        $id = (int) $connection->id;
        $ticket = $request->get('ticket');
        $claims = is_string($ticket) && $ticket !== '' ? $this->tickets()->consume($ticket) : null;
        if ($claims === null) {
            $this->meta[$id] = ['reject_code' => self::CLOSE_TICKET, 'reject_reason' => 'invalid_ticket'];

            return;
        }

        $adminId = $claims['admin_id'];
        if ($claims['ver'] !== ($this->versionOf)($adminId) || ($this->isRevoked)($claims['jti'])) {
            $this->meta[$id] = ['reject_code' => self::CLOSE_REVOKED, 'reject_reason' => 'revoked'];

            return;
        }

        $field = sprintf('%s:%d:%d', gethostname() ?: 'unknown', getmypid() ?: 0, $id);
        $this->registry->add($adminId, $connection);
        $this->meta[$id] = [
            'admin_id'  => $adminId,
            'ver'       => $claims['ver'],
            'jti'       => $claims['jti'],
            'field'     => $field,
            'last_ping' => time(),
        ];
        $this->presence()->join($adminId, $field, [
            'ip'           => $claims['ip'],
            'ua'           => $claims['ua'],
            'connected_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** 握手响应发出之后：被拒的连接带关闭码关闭，通过的连接下发 connected。 */
    public function afterHandshake(object $connection): void
    {
        $meta = $this->meta[(int) $connection->id] ?? null;
        if ($meta === null || isset($meta['reject_code'])) {
            unset($this->meta[(int) $connection->id]);
            $this->closeWith(
                $connection,
                (int) ($meta['reject_code'] ?? self::CLOSE_TICKET),
                (string) ($meta['reject_reason'] ?? 'invalid_ticket'),
            );

            return;
        }

        $connection->send($this->frame('connected', [
            'admin_id'  => (int) $meta['admin_id'],
            'heartbeat' => self::HEARTBEAT_INTERVAL,
        ]));
    }

    /** 客户端只允许发 {event:'ping'}：回 pong 并续期在线状态，其余一律忽略。 */
    public function handleMessage(object $connection, string $data): void
    {
        $id = (int) $connection->id;
        if (!isset($this->meta[$id]['admin_id'])) {
            return;
        }
        $decoded = json_decode($data, true);
        if (!is_array($decoded) || ($decoded['event'] ?? null) !== 'ping') {
            return;
        }

        $this->meta[$id]['last_ping'] = time();
        $connection->send($this->frame('pong', []));
        $this->presence()->touch((int) $this->meta[$id]['admin_id']);
    }

    public function handleClose(object $connection): void
    {
        $id = (int) $connection->id;
        $meta = $this->meta[$id] ?? null;
        unset($this->meta[$id]);
        $this->registry->remove($connection);
        if (isset($meta['admin_id'], $meta['field'])) {
            $this->presence()->leave((int) $meta['admin_id'], (string) $meta['field']);
        }
    }

    /** 订阅回调：只投递本进程内匹配的连接；force_logout 先下发再以 4003 关闭。 */
    public function dispatch(string $raw): void
    {
        $message = RealtimeMessage::decode($raw);
        if ($message === null) {
            return;
        }

        $frame = json_encode($message->frame(), JSON_UNESCAPED_UNICODE) ?: '';
        foreach ($this->registry->forTargets($message->targets) as $connection) {
            $connection->send($frame);
            if ($message->event === 'force_logout') {
                $this->closeWith($connection, self::CLOSE_REVOKED, 'force_logout');
                $this->handleClose($connection);
            }
        }
    }

    /** 关闭最后一次 ping 距 $now 超过 HEARTBEAT_TIMEOUT 秒的连接。 */
    public function sweep(int $now): void
    {
        foreach ($this->registry->all() as $connection) {
            $meta = $this->meta[(int) $connection->id] ?? null;
            if ($meta !== null && $now - (int) $meta['last_ping'] > self::HEARTBEAT_TIMEOUT) {
                $this->closeWith($connection, self::CLOSE_HEARTBEAT, 'heartbeat_timeout');
                $this->handleClose($connection);
            }
        }
    }

    /** 版本号变化（禁用 / 删除 / 改密码 / 强制下线）或 jti 被拉黑（登出）的连接：下发 force_logout 后 4003 关闭。 */
    public function recheckRevocation(): void
    {
        $versions = [];
        foreach ($this->registry->all() as $connection) {
            $meta = $this->meta[(int) $connection->id] ?? null;
            if ($meta === null) {
                continue;
            }
            $adminId = (int) $meta['admin_id'];
            $versions[$adminId] ??= ($this->versionOf)($adminId);
            if ($versions[$adminId] === (int) $meta['ver'] && !($this->isRevoked)((string) $meta['jti'])) {
                continue;
            }

            $connection->send($this->frame('force_logout', ['reason' => 'revoked', 'message' => lang('auth.token_expired')]));
            $this->closeWith($connection, self::CLOSE_REVOKED, 'revoked');
            $this->handleClose($connection);
        }
    }

    /**
     * 发 RFC 6455 close 帧后关闭。服务端帧不加掩码，原样发送（raw = true）——与 vendor 回 close 帧的写法一致
     * （Protocols/Websocket.php: $connection->close("\x88\x02\x03\xe8", true)）。不带 raw 会被再编码成文本帧。
     */
    public function closeWith(object $connection, int $code, string $reason): void
    {
        $reason = substr($reason, 0, self::MAX_REASON_BYTES);
        $connection->close("\x88" . chr(2 + strlen($reason)) . pack('n', $code) . $reason, true);
    }

    // ------------------------------------------------------------------ 内部

    private function subscribe(): void
    {
        $config = (array) config('redis.default', []);
        $client = new AsyncRedis(sprintf('redis://%s:%d', (string) ($config['host'] ?? '127.0.0.1'), (int) ($config['port'] ?? 6379)));
        $password = (string) ($config['password'] ?? '');
        if ($password !== '') {
            $client->auth($password);
        }
        $database = (int) ($config['database'] ?? 0);
        if ($database !== 0) {
            $client->select($database);
        }
        $client->subscribe([RealtimePublisher::CHANNEL], function ($channel, $message): void {
            $this->guard('ws.dispatch', fn () => $this->dispatch((string) $message));
        });
        $this->subscriber = $client;
        // 读一次 $this->subscriber（而不是局部变量 $client）：这个属性存在的唯一目的是持有异步客户端的
        // 引用防止被 GC 回收，phpstan 单看写入看不出这层用途，会判它「只写不读」（property.onlyWritten）。
        Log::info('ws.subscribed', ['channel' => RealtimePublisher::CHANNEL, 'client' => spl_object_id($this->subscriber)]);
    }

    /** @param array<string, mixed> $payload */
    private function frame(string $event, array $payload): string
    {
        return json_encode([
            'event'   => $event,
            'payload' => $payload === [] ? new \stdClass() : $payload,
            'id'      => bin2hex(random_bytes(8)),
            'ts'      => time(),
        ], JSON_UNESCAPED_UNICODE) ?: '';
    }

    private function tickets(): WsTicketService
    {
        return $this->tickets ?? Container::get(WsTicketService::class);
    }

    private function presence(): PresenceService
    {
        return $this->presence ?? Container::get(PresenceService::class);
    }

    private function guard(string $label, \Closure $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            Log::error($label . '_failed', ['error' => mb_substr($e->getMessage(), 0, 500)]);
        } finally {
            Context::destroy();
        }
    }
}
