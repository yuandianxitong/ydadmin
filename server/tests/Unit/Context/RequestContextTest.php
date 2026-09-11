<?php

declare(strict_types=1);

namespace tests\Unit\Context;

use core\context\RequestContext;
use support\Context;
use tests\TestCase;

final class RequestContextTest extends TestCase
{
    public function test_valid_inbound_trace_is_kept(): void
    {
        // admin 前端生成的格式：'trace_' + 毫秒时间戳 + '_' + 9 位随机串
        $trace = 'trace_1726000000000_abc123def';
        $this->assertSame($trace, RequestContext::initTrace($trace));
        $this->assertSame($trace, RequestContext::traceId());
    }

    public function test_invalid_inbound_trace_is_replaced(): void
    {
        foreach (['abc', "abcdefgh\n", 'has space here', str_repeat('a', 129)] as $bad) {
            $trace = RequestContext::initTrace($bad);
            $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $trace, "输入：" . json_encode($bad));
        }
    }

    public function test_missing_inbound_trace_is_generated(): void
    {
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', RequestContext::initTrace(null));
    }

    public function test_defaults_and_reset(): void
    {
        $this->assertSame('', RequestContext::traceId());
        $this->assertSame(0, RequestContext::actingUser());

        RequestContext::initTrace(null);
        RequestContext::setActingUser(7);
        $this->assertSame(7, RequestContext::actingUser());

        Context::destroy();
        $this->assertSame('', RequestContext::traceId());
        $this->assertSame(0, RequestContext::actingUser());
    }
}
