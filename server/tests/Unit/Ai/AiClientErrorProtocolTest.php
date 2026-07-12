<?php
// server/tests/Unit/Ai/AiClientErrorProtocolTest.php
namespace tests\Unit\Ai;

use app\command\YdAiCommand;
use core\ai\AiClientException;
use core\ai\SchemaException;
use tests\TestCase;

class AiClientErrorProtocolTest extends TestCase
{
    public function testExceptionCarriesCodeAndRequestId(): void
    {
        $e = new AiClientException('额度耗尽', 'QUOTA_EXHAUSTED', 'req_abc123');
        $this->assertSame('QUOTA_EXHAUSTED', $e->getErrorCode());
        $this->assertSame('req_abc123', $e->getRequestId());
    }

    public function testExceptionDefaultsAreEmpty(): void
    {
        $e = new AiClientException('旧式调用');
        $this->assertSame('', $e->getErrorCode());
        $this->assertSame('', $e->getRequestId());
    }

    /**
     * 退出码契约（engine-protocol.md 第 4 节）：INPUT_* 与 Schema 问题 → 1，其余 → 2
     */
    public function testExitCodeMapping(): void
    {
        $cmd = new class extends YdAiCommand {
            public function callExitCodeFor(AiClientException $e): int
            {
                return $this->exitCodeFor($e);
            }
        };

        $this->assertSame(1, $cmd->callExitCodeFor(new SchemaException('表不存在')));
        $this->assertSame(1, $cmd->callExitCodeFor(new AiClientException('参数错误', 'INPUT_INVALID', 'req_1')));
        $this->assertSame(1, $cmd->callExitCodeFor(new AiClientException('框架不支持', 'INPUT_UNSUPPORTED_FRAMEWORK', 'req_2')));
        $this->assertSame(2, $cmd->callExitCodeFor(new AiClientException('额度耗尽', 'QUOTA_EXHAUSTED', 'req_3')));
        $this->assertSame(2, $cmd->callExitCodeFor(new AiClientException('无码旧式错误')));
    }
}
