<?php
declare(strict_types=1);

namespace tests\Feature\Ai;

use app\adminapi\controller\v1\system\YdSpecCompileController;
use app\service\system\YdSpecCompileService;
use tests\TestCase;

class YdSpecCompileControllerTest extends TestCase
{
    public function testCompileEndpointReturnsStage(): void
    {
        $stub = new class extends YdSpecCompileService {
            public function compile(string $specId): array
            {
                return [
                    'stage_id' => 'compile_' . str_repeat('a', 16),
                    'dir' => 'runtime/ai/specs/x/compile_x',
                    'schema_patch' => 'CREATE',
                    'update_sql' => 'CREATE',
                    'files' => [],
                ];
            }
        };
        $controller = new class($stub) extends YdSpecCompileController {
            public function __construct(YdSpecCompileService $service)
            {
                parent::__construct();
                $this->ydSpecCompileService = $service;
            }
        };

        $this->app->request->withPost(['spec_id' => 'spec_' . str_repeat('a', 16)]);
        $response = $controller->compile();
        $data = json_decode((string) $response->getContent(), true);

        $this->assertSame(200, $data['code']);
        $this->assertStringStartsWith('compile_', $data['data']['stage_id']);
    }

    public function testCompileEndpointRejectsMissingSpecId(): void
    {
        $this->app->request->withPost([]);
        $response = $this->app->make(YdSpecCompileController::class)->compile();
        $data = json_decode((string) $response->getContent(), true);

        $this->assertNotSame(200, $data['code']);
    }
}
