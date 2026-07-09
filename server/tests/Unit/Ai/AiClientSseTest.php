<?php
// server/tests/Unit/Ai/AiClientSseTest.php
namespace tests\Unit\Ai;

use core\ai\AiClient;
use tests\TestCase;

class AiClientSseTest extends TestCase
{
    public function testParseChunkAndDoneEvents(): void
    {
        $raw = "event: chunk\ndata: {\"content\": \"a: b\"}\n\n"
             . "event: done\ndata: {\"generation_id\": \"gen_1\", \"files\": [{\"path\": \"server/app/service/goods/GoodsService.php\", \"code\": \"<?php\"}], \"tokens_used\": 42}\n\n";
        $events = AiClient::parseSseBuffer($raw);
        $this->assertCount(2, $events);
        $this->assertSame('chunk', $events[0]['event']);
        $this->assertSame('a: b', $events[0]['data']['content']);
        $this->assertSame('done', $events[1]['event']);
        $this->assertSame(42, $events[1]['data']['tokens_used']);
        $this->assertSame('server/app/service/goods/GoodsService.php', $events[1]['data']['files'][0]['path']);
    }

    public function testParseErrorEvent(): void
    {
        $raw = "event: error\ndata: {\"message\": \"配额耗尽\"}\n\n";
        $events = AiClient::parseSseBuffer($raw);
        $this->assertSame('error', $events[0]['event']);
        $this->assertSame('配额耗尽', $events[0]['data']['message']);
    }

    public function testMalformedDataLineSkipped(): void
    {
        $raw = "event: done\ndata: {不是json}\n\n";
        $events = AiClient::parseSseBuffer($raw);
        $this->assertSame([], $events); // 非法 data 行跳过，不抛异常
    }
}
