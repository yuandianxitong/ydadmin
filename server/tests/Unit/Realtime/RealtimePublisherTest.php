<?php

declare(strict_types=1);

namespace tests\Unit\Realtime;

use core\realtime\RealtimeMessage;
use core\realtime\RealtimePublisher;
use tests\TestCase;

/** 记下每次 publishRaw() 的参数；$fail 为真时模拟 Redis 不可用。 */
final class SpyRealtimePublisher extends RealtimePublisher
{
    /** @var list<array{channel: string, message: string}> */
    public array $sent = [];

    public function __construct(private readonly bool $fail = false)
    {
    }

    protected function publishRaw(string $channel, string $message): void
    {
        if ($this->fail) {
            throw new \RuntimeException('Redis 不可用');
        }
        $this->sent[] = ['channel' => $channel, 'message' => $message];
    }
}

final class RealtimePublisherTest extends TestCase
{
    public function test_publish_sends_an_encoded_message_to_the_channel(): void
    {
        $spy = new SpyRealtimePublisher();

        $spy->publish([7, 8], 'notification.created', ['id' => 5, 'title' => '标题']);

        $this->assertCount(1, $spy->sent);
        $this->assertSame('realtime:admin', $spy->sent[0]['channel']);
        $decoded = RealtimeMessage::decode($spy->sent[0]['message']);
        $this->assertNotNull($decoded);
        $this->assertSame([7, 8], $decoded->targets);
        $this->assertSame(['id' => 5, 'title' => '标题'], $decoded->payload);
    }

    public function test_failures_and_invalid_input_never_escape(): void
    {
        (new SpyRealtimePublisher(true))->publish('all', 'notification.created', ['id' => 1]);
        $invalid = new SpyRealtimePublisher();
        $invalid->publish('all', 'not.an.event', []);

        $this->assertSame([], $invalid->sent, '非法事件不发送');
        $this->addToAssertionCount(1);
    }

    public function test_progress_clamps_percent_and_targets_one_admin(): void
    {
        $spy = new SpyRealtimePublisher();

        $spy->progress(3, 'job-1', 150, '快完成了');

        $decoded = RealtimeMessage::decode($spy->sent[0]['message']);
        $this->assertNotNull($decoded);
        $this->assertSame([3], $decoded->targets);
        $this->assertSame('task.progress', $decoded->event);
        $this->assertSame(['task_id' => 'job-1', 'percent' => 100, 'message' => '快完成了'], $decoded->payload);
    }

    public function test_publish_reaches_a_real_redis_subscriber(): void
    {
        $host = (string) config('redis.default.host');
        $port = (int) config('redis.default.port');
        $password = (string) config('redis.default.password');
        $socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 2);
        $this->assertIsResource($socket, "连不上 Redis：{$errstr}");
        stream_set_timeout($socket, 2);

        try {
            if ($password !== '') {
                fwrite($socket, self::resp(['AUTH', $password]));
                fgets($socket);
            }
            fwrite($socket, self::resp(['SUBSCRIBE', RealtimePublisher::CHANNEL]));
            $this->assertSame(['subscribe', RealtimePublisher::CHANNEL, 1], self::readArray($socket));

            (new RealtimePublisher())->publish([42], 'force_logout', ['reason' => 'kicked']);

            $frame = self::readArray($socket);
            $this->assertFalse(stream_get_meta_data($socket)['timed_out'], '2 秒内没有收到发布的消息');
            $this->assertSame('message', $frame[0]);
            $this->assertSame(RealtimePublisher::CHANNEL, $frame[1]);
            $decoded = RealtimeMessage::decode((string) $frame[2]);
            $this->assertNotNull($decoded);
            $this->assertSame([42], $decoded->targets);
            $this->assertSame('force_logout', $decoded->event);
        } finally {
            fclose($socket);
        }
    }

    /** @param list<string> $parts */
    private static function resp(array $parts): string
    {
        $out = '*' . count($parts) . "\r\n";
        foreach ($parts as $part) {
            $out .= '$' . strlen($part) . "\r\n{$part}\r\n";
        }

        return $out;
    }

    /**
     * 读一个 RESP 数组帧（元素只可能是批量字符串或整数）；读超时返回已读到的部分。
     *
     * @param resource $socket
     * @return list<int|string>
     */
    private static function readArray($socket): array
    {
        $header = fgets($socket);
        if (!is_string($header) || !str_starts_with($header, '*')) {
            return [];
        }
        $items = [];
        for ($i = 0, $n = (int) substr($header, 1); $i < $n; $i++) {
            $line = fgets($socket);
            if (!is_string($line)) {
                break;
            }
            if ($line[0] === ':') {
                $items[] = (int) substr($line, 1);

                continue;
            }
            $length = (int) substr($line, 1);
            $data = '';
            while (strlen($data) < $length + 2) {
                $chunk = fread($socket, $length + 2 - strlen($data));
                if ($chunk === false || $chunk === '') {
                    break;
                }
                $data .= $chunk;
            }
            $items[] = substr($data, 0, $length);
        }

        return $items;
    }
}
