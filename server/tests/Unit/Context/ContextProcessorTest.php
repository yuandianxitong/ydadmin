<?php

declare(strict_types=1);

namespace tests\Unit\Context;

use app\log\ContextProcessor;
use core\context\RequestContext;
use tests\TestCase;

final class ContextProcessorTest extends TestCase
{
    public function test_adds_trace_and_acting_user_when_present(): void
    {
        RequestContext::initTrace('trace_1726000000000_abc123def');
        RequestContext::setActingUser(9);

        $record = (new ContextProcessor())(['message' => 'x', 'extra' => []]);

        $this->assertSame('trace_1726000000000_abc123def', $record['extra']['trace_id']);
        $this->assertSame(9, $record['extra']['acting_user']);
    }

    public function test_omits_empty_values(): void
    {
        $record = (new ContextProcessor())(['message' => 'x', 'extra' => []]);

        $this->assertArrayNotHasKey('trace_id', $record['extra']);
        $this->assertArrayNotHasKey('acting_user', $record['extra']);
    }

    public function test_processor_is_wired_into_default_log_channel(): void
    {
        $classes = array_column((array) config('log.default.processors', []), 'class');
        $this->assertContains(ContextProcessor::class, $classes, 'processor 必须挂到 config/log.php 的 default 通道');
    }
}
