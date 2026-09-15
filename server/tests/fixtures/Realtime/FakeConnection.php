<?php

declare(strict_types=1);

namespace tests\fixtures\Realtime;

/**
 * WebSocketServer 测试用假连接：只提供 WebSocketServer 用到的成员（id、send、close、onWebSocketConnected），
 * 记录下发的帧与关闭时带的原始数据。close() 带 raw=true 时解析 close 帧，得到关闭码与原因，
 * 便于断言 closeWith() 走的是 RFC 6455 close 帧而不是文本帧。
 */
final class FakeConnection
{
    private static int $nextId = 1;

    public int $id;

    /** @var list<string> 下发的文本帧（未解码的 JSON 字符串） */
    public array $sent = [];

    public bool $closed = false;

    public ?int $closeCode = null;

    public string $closeReason = '';

    /** @var (callable(object): void)|null vendor 在握手响应发出后调用 */
    public $onWebSocketConnected = null;

    public function __construct()
    {
        $this->id = self::$nextId++;
    }

    public function send(mixed $data, bool $raw = false): bool
    {
        $this->sent[] = (string) $data;

        return true;
    }

    public function close(mixed $data = null, bool $raw = false): void
    {
        $this->closed = true;
        if ($raw && is_string($data) && strlen($data) >= 4 && $data[0] === "\x88") {
            $length = ord($data[1]);
            $this->closeCode = unpack('n', substr($data, 2, 2))[1];
            $this->closeReason = substr($data, 4, $length - 2);
        }
    }

    /** @return list<array<string, mixed>> 解码后的帧 */
    public function frames(): array
    {
        return array_map(static fn (string $raw): array => (array) json_decode($raw, true), $this->sent);
    }

    /** @return list<string> 按顺序的 event 名 */
    public function events(): array
    {
        return array_map(static fn (array $frame): string => (string) ($frame['event'] ?? ''), $this->frames());
    }
}
