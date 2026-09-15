<?php

/**
 * WebSocket 实时通道冒烟（spec §8「真实环境」）：对运行中的服务走一遍「取票据 → 握手 → connected →
 * ping/pong → 服务端推送一条 task.progress 并收到」。
 *
 * 用法：WS_SMOKE_TOKEN=<admin token> php scripts/ws-smoke.php [http_base] [ws_url]
 *   http_base 默认 http://127.0.0.1:8000；ws_url 默认 ws://127.0.0.1:8001/ws
 *   token 只从环境变量读、从不打印。本脚本不写数据库：取票据接口不记操作日志（admin_log.skip），
 *   推送只往 Redis 频道 PUBLISH。
 * 退出码：0 = 通过；1 = 失败（失败原因写 stderr）。
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once dirname(__DIR__) . '/support/bootstrap.php';

use core\realtime\RealtimePublisher;

$httpBase = rtrim((string) ($argv[1] ?? 'http://127.0.0.1:8000'), '/');
$wsUrl = (string) ($argv[2] ?? 'ws://127.0.0.1:8001/ws');
$token = (string) getenv('WS_SMOKE_TOKEN');

function fail(string $message): never
{
    fwrite(STDERR, "✗ {$message}\n");
    exit(1);
}

function pass(string $message): void
{
    echo "✓ {$message}\n";
}

if ($token === '') {
    fail('缺少环境变量 WS_SMOKE_TOKEN');
}

// 1. 取票据
$ch = curl_init("{$httpBase}/adminapi/ws/ticket");
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => '{}',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 5,
    CURLOPT_HTTPHEADER     => ['Accept: application/json', 'Content-Type: application/json', "Authorization: Bearer {$token}"],
]);
$body = (string) curl_exec($ch);
curl_close($ch);
$json = json_decode($body, true);
$ticket = is_array($json) ? (string) ($json['data']['ticket'] ?? '') : '';
if (!is_array($json) || ($json['code'] ?? null) !== 200 || $ticket === '') {
    fail('取票据失败：' . mb_substr($body, 0, 200));
}
pass('取到票据（expires_in=' . (int) ($json['data']['expires_in'] ?? 0) . '）');

// 2. 握手
$parts = parse_url($wsUrl);
$host = is_array($parts) ? (string) ($parts['host'] ?? '127.0.0.1') : '127.0.0.1';
$port = is_array($parts) ? (int) ($parts['port'] ?? 80) : 80;
$path = is_array($parts) ? (string) ($parts['path'] ?? '/ws') : '/ws';

/** @var resource|false $socket */
$socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 5);
if ($socket === false) {
    fail("连不上 {$host}:{$port}：{$errstr}");
}
stream_set_timeout($socket, 5);
$key = base64_encode(random_bytes(16));
fwrite($socket, "GET {$path}?ticket=" . rawurlencode($ticket) . " HTTP/1.1\r\n"
    . "Host: {$host}:{$port}\r\nUpgrade: websocket\r\nConnection: Upgrade\r\n"
    . "Sec-WebSocket-Key: {$key}\r\nSec-WebSocket-Version: 13\r\n\r\n");
$head = '';
while (!str_contains($head, "\r\n\r\n")) {
    $chunk = fread($socket, 1);
    if ($chunk === '' || $chunk === false) {
        fail('握手响应读取超时：' . $head);
    }
    $head .= $chunk;
}
$expectedAccept = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
if (!str_starts_with($head, 'HTTP/1.1 101') || stripos($head, "Sec-WebSocket-Accept: {$expectedAccept}") === false) {
    fail('握手失败：' . strtok($head, "\r\n"));
}
pass('握手 101');

/**
 * 读 $length 字节，读不满 / 超时即失败退出。
 *
 * @param resource $socket
 */
function readExactly($socket, int $length): string
{
    $data = '';
    while (strlen($data) < $length) {
        $chunk = fread($socket, $length - strlen($data));
        if ($chunk === '' || $chunk === false) {
            fail('读帧超时');
        }
        $data .= $chunk;
    }

    return $data;
}

/**
 * 读一帧（服务端帧不带掩码）；返回 [opcode, payload]。
 *
 * @param resource $socket
 * @return array{0: int, 1: string}
 */
function readFrame($socket): array
{
    $head = readExactly($socket, 2);
    $bytes = unpack('C2', $head);
    if ($bytes === false || !isset($bytes[1], $bytes[2])) {
        fail('读帧头失败');
    }
    $opcode = $bytes[1] & 0x0F;
    $length = $bytes[2] & 0x7F;
    if ($length === 126) {
        $extended = unpack('n', readExactly($socket, 2));
        if ($extended === false || !isset($extended[1])) {
            fail('读扩展长度失败');
        }
        $length = $extended[1];
    } elseif ($length === 127) {
        $extended = unpack('J', readExactly($socket, 8));
        if ($extended === false || !isset($extended[1])) {
            fail('读扩展长度失败');
        }
        $length = $extended[1];
    }

    return [$opcode, $length > 0 ? readExactly($socket, $length) : ''];
}

/**
 * 读下一个 JSON 数据帧（跳过控制帧）；遇到 close 帧直接失败并打印关闭码。
 *
 * @param resource $socket
 * @return array<string, mixed>
 */
function readEvent($socket): array
{
    for ($i = 0; $i < 10; $i++) {
        [$opcode, $payload] = readFrame($socket);
        if ($opcode === 0x8) {
            $codeBytes = strlen($payload) >= 2 ? unpack('n', substr($payload, 0, 2)) : false;
            $code = ($codeBytes !== false && isset($codeBytes[1])) ? $codeBytes[1] : 0;
            fail("服务端关闭连接，关闭码 {$code}");
        }
        if ($opcode === 0x1 || $opcode === 0x2) {
            $frame = json_decode($payload, true);
            if (!is_array($frame)) {
                fail('收到非 JSON 数据帧');
            }

            return $frame;
        }
    }
    fail('连续 10 帧都不是数据帧');
}

/**
 * 发一个带掩码的文本帧（客户端帧必须带掩码）。
 *
 * @param resource $socket
 */
function sendText($socket, string $text): void
{
    $mask = random_bytes(4);
    $length = strlen($text);
    $header = chr(0x81) . ($length < 126 ? chr(0x80 | $length) : chr(0x80 | 126) . pack('n', $length));
    $masked = '';
    for ($i = 0; $i < $length; $i++) {
        $masked .= $text[$i] ^ $mask[$i % 4];
    }
    fwrite($socket, $header . $mask . $masked);
}

// 3. connected
$connected = readEvent($socket);
$connectedPayload = is_array($connected['payload'] ?? null) ? $connected['payload'] : [];
$adminId = (int) ($connectedPayload['admin_id'] ?? 0);
if (($connected['event'] ?? '') !== 'connected' || $adminId <= 0) {
    fail('首帧不是 connected：' . (string) json_encode($connected, JSON_UNESCAPED_UNICODE));
}
pass("收到 connected（admin_id={$adminId}，heartbeat=" . (int) ($connectedPayload['heartbeat'] ?? 0) . '）');

// 4. ping / pong
sendText($socket, '{"event":"ping"}');
$pong = readEvent($socket);
if (($pong['event'] ?? '') !== 'pong') {
    fail('ping 后没有收到 pong：' . (string) json_encode($pong, JSON_UNESCAPED_UNICODE));
}
pass('ping → pong');

// 5. 服务端推送（经 Redis 频道 → websocket worker → 本连接）
$taskId = 'ws-smoke-' . bin2hex(random_bytes(4));
(new RealtimePublisher())->progress($adminId, $taskId, 100, 'ws-smoke');
$progress = readEvent($socket);
$progressPayload = is_array($progress['payload'] ?? null) ? $progress['payload'] : [];
if (($progress['event'] ?? '') !== 'task.progress' || ($progressPayload['task_id'] ?? '') !== $taskId) {
    fail('没有收到推送的 task.progress：' . (string) json_encode($progress, JSON_UNESCAPED_UNICODE));
}
pass('收到经 Redis 频道推送的 task.progress');

// 6. 正常关闭（1000）
$mask = random_bytes(4);
$closePayload = pack('n', 1000);
$masked = '';
for ($i = 0; $i < 2; $i++) {
    $masked .= $closePayload[$i] ^ $mask[$i % 4];
}
fwrite($socket, chr(0x88) . chr(0x80 | 2) . $mask . $masked);
fclose($socket);

echo "\n冒烟通过\n";
exit(0);
