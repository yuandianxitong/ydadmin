<?php
// tests/Feature/Ai/YdSpecCompileControllerGateTest.php
declare(strict_types=1);

namespace tests\Feature\Ai;

use app\adminapi\controller\v1\system\YdSpecCompileController;
use app\service\system\AiArtifactService;
use app\service\system\YdSpecCompileService;
use tests\TestCase;

class YdSpecCompileControllerGateTest extends TestCase
{
    public function testApplyDelegatesToArtifactService(): void
    {
        $fakeArtifact = new class extends AiArtifactService {
            public array $applied = [];
            public function __construct() {}
            public function applyArtifact(int $artifactId, ?string $projectRootOverride = null): array
            {
                $this->applied[] = $artifactId;
                return ['applied' => true, 'written' => ['app/model/x/X.php']];
            }
        };
        $controller = new class($fakeArtifact) extends YdSpecCompileController {
            public function __construct(AiArtifactService $a) { parent::__construct(); $this->aiArtifactService = $a; }
        };

        $this->app->request->withPost(['artifact_id' => 7]);
        $resp = $controller->apply(7);
        $data = json_decode((string) $resp->getContent(), true);
        $this->assertSame(200, $data['code']);
        $this->assertTrue($data['data']['applied']);
    }

    public function testApplyRejectsMissingId(): void
    {
        $resp = $this->app->make(YdSpecCompileController::class)->apply(0);
        $data = json_decode((string) $resp->getContent(), true);
        $this->assertNotSame(200, $data['code']);
    }
}
