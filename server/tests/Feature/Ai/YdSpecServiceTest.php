<?php
// tests/Feature/Ai/YdSpecServiceTest.php
declare(strict_types=1);

namespace tests\Feature\Ai;

use app\service\system\YdSpecService;
use core\ai\AiClient;
use core\exception\BusinessException;
use tests\TestCase;

class YdSpecServiceTest extends TestCase
{
    private function validSpec(): array
    {
        return [
            'version' => 'ydspec/v1',
            'module'  => ['name' => 'appointment', 'title' => '预约管理'],
            'entities' => [[
                'name' => 'Appointment', 'table' => 'appointments',
                'kind' => 'business', 'soft_delete' => 'soft',
                'fields' => [['name' => 'title', 'type' => 'string', 'nullable' => false]],
            ]],
        ];
    }

    private function serviceReturning(array $engineResponse): YdSpecService
    {
        $fakeClient = new class('http://engine.test') extends AiClient {
            public array $canned = [];
            protected function post(string $path, array $payload, ?callable $onWrite = null): string
            {
                return json_encode($this->canned);
            }
        };
        $fakeClient->canned = $engineResponse;

        return new class($fakeClient) extends YdSpecService {
            public function __construct(private AiClient $injected) { parent::__construct(); }
            protected function makeClient(): AiClient { return $this->injected; }
        };
    }

    public function testRefineMergesSemanticIssues(): void
    {
        $spec = $this->validSpec();
        $spec['entities'][0]['fields'][] = ['name' => 'created_at', 'type' => 'datetime'];
        $service = $this->serviceReturning(['draft_spec' => $spec, 'questions' => [], 'explanations' => []]);
        $out = $service->refine('做预约', [], null);
        $this->assertContains('reserved-field', array_column($out['issues'], 'rule'));
    }

    public function testConfirmPersistsValidSpec(): void
    {
        $service = $this->serviceReturning([]);
        $out = $service->confirm($this->validSpec());
        $this->assertStringStartsWith('spec_', $out['spec_id']);
        $file = rtrim(root_path(), '/') . '/' . $out['path'];
        $this->assertFileExists($file);
        @unlink($file);
        @rmdir(dirname($file));
    }

    public function testConfirmRejectsBlockingSpec(): void
    {
        $service = $this->serviceReturning([]);
        $bad = $this->validSpec();
        $bad['entities'][0]['fields'][] = ['name' => 'total_fee', 'type' => 'int']; // money-decimal error
        $this->expectException(BusinessException::class);
        $service->confirm($bad);
    }
}
