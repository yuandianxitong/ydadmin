<?php
// tests/Unit/YdSpec/AiClientSpecRefineTest.php
declare(strict_types=1);

namespace tests\Unit\YdSpec;

use core\ai\AiClient;
use tests\TestCase;

class AiClientSpecRefineTest extends TestCase
{
    public function testSpecRefineDecodesJson(): void
    {
        $client = new class('http://engine.test') extends AiClient {
            protected function post(string $path, array $payload, ?callable $onWrite = null): string
            {
                return json_encode([
                    'draft_spec' => ['version' => 'ydspec/v1'],
                    'questions' => [],
                    'explanations' => [],
                    'versions' => ['prompt' => 'spec_refine/v1'],
                ]);
            }
        };
        $result = $client->specRefine('做预约', [], null, 'proj_x');
        $this->assertSame('ydspec/v1', $result['draft_spec']['version']);
        $this->assertSame('spec_refine/v1', $result['versions']['prompt']);
    }
}
