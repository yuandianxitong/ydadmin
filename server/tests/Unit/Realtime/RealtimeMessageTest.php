<?php

declare(strict_types=1);

namespace tests\Unit\Realtime;

use core\realtime\RealtimeMessage;
use tests\TestCase;

final class RealtimeMessageTest extends TestCase
{
    public function test_constructs_with_generated_id_and_timestamp(): void
    {
        $before = time();
        $message = new RealtimeMessage('all', 'notification.created', ['id' => 1]);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $message->id);
        $this->assertGreaterThanOrEqual($before, $message->ts);
        $this->assertSame('all', $message->targets);
    }

    public function test_targets_are_normalised_to_a_unique_int_list(): void
    {
        $message = new RealtimeMessage([3, 3, 5], 'force_logout', []);

        $this->assertSame([3, 5], $message->targets);
    }

    public function test_rejects_unknown_event_and_bad_targets(): void
    {
        foreach ([
            static fn () => new RealtimeMessage('all', 'unknown.event', []),
            static fn () => new RealtimeMessage('everyone', 'force_logout', []),
            static fn () => new RealtimeMessage([], 'force_logout', []),
            static fn () => new RealtimeMessage([0], 'force_logout', []),
            static fn () => new RealtimeMessage(['1'], 'force_logout', []),
        ] as $i => $build) {
            try {
                $build();
                $this->fail("第 {$i} 个非法组合没有抛异常");
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_encode_decode_round_trip_keeps_every_field(): void
    {
        $message = new RealtimeMessage([7], 'task.progress', ['task_id' => 't1', 'percent' => 40, 'message' => '处理中'], 'abc123', 1789000000);

        $decoded = RealtimeMessage::decode($message->encode());

        $this->assertNotNull($decoded);
        $this->assertSame([7], $decoded->targets);
        $this->assertSame('task.progress', $decoded->event);
        $this->assertSame(['task_id' => 't1', 'percent' => 40, 'message' => '处理中'], $decoded->payload);
        $this->assertSame('abc123', $decoded->id);
        $this->assertSame(1789000000, $decoded->ts);
    }

    public function test_encode_failure_becomes_runtime_exception(): void
    {
        $message = new RealtimeMessage('all', 'notification.created', ['title' => "\xFF"]);

        $this->expectException(\RuntimeException::class);
        $message->encode();
    }

    public function test_decode_returns_null_for_anything_invalid(): void
    {
        $this->assertNull(RealtimeMessage::decode('not json'));
        $this->assertNull(RealtimeMessage::decode('[]'));
        $this->assertNull(RealtimeMessage::decode('{"targets":"all","event":"nope","payload":{},"id":"x","ts":1}'));
        $this->assertNull(RealtimeMessage::decode('{"targets":"all","event":"force_logout","payload":"str","id":"x","ts":1}'));
        $this->assertNull(RealtimeMessage::decode('{"targets":[1],"event":"force_logout","payload":{},"ts":1}'));
    }

    public function test_targets_admin_and_frame(): void
    {
        $all = new RealtimeMessage('all', 'notification.created', ['id' => 9], 'id1', 100);
        $some = new RealtimeMessage([1, 2], 'force_logout', ['reason' => 'kicked'], 'id2', 200);

        $this->assertTrue($all->targetsAdmin(42));
        $this->assertTrue($some->targetsAdmin(2));
        $this->assertFalse($some->targetsAdmin(3));
        $this->assertSame(['event' => 'force_logout', 'payload' => ['reason' => 'kicked'], 'id' => 'id2', 'ts' => 200], $some->frame());
    }
}
