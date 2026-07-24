<?php
// tests/Feature/Ai/YdSpecApplyDevTest.php
declare(strict_types=1);

namespace tests\Feature\Ai;

use app\service\system\AiArtifactService;
use app\service\system\YdSpecCompileService;
use tests\TestCase;

class YdSpecApplyDevTest extends TestCase
{
    private array $cleanupDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->cleanupDirs as $dir) {
            $this->rmrf($dir);
        }
        parent::tearDown();
    }

    private function rmrf(string $dir): void
    {
        if (!is_dir($dir)) { return; }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }

    /**
     * 假 AiArtifactService：record/runChecks 走 canned 数据；findByStage 返回内存伪 artifact；
     * applyArtifact 直接调用继承来的 materialize()（跳过状态机 claim），并覆盖 runDdl 避免连真库。
     */
    private function service(): YdSpecCompileService
    {
        $fakeArtifact = new class extends AiArtifactService {
            public array $ddlRuns = [];
            private ?array $lastArt = null;
            public function __construct() {}
            public function record(string $specId, string $stageId, string $module, string $title): int
            {
                return 1;
            }
            public function runChecks(int $artifactId): array
            {
                return ['artifact_id' => $artifactId, 'state' => 'checked_passed', 'check_summary' => ['passed' => true, 'error_count' => 0, 'warning_count' => 0, 'skipped' => [], 'results' => []]];
            }
            public function findByStage(string $specId, string $stageId): ?array
            {
                $this->lastArt = ['id' => 1, 'spec_id' => $specId, 'stage_id' => $stageId];
                return $this->lastArt;
            }
            public function applyArtifact(int $artifactId, ?string $projectRootOverride = null): array
            {
                $written = $this->materialize($this->lastArt, $projectRootOverride);
                return ['applied' => true, 'written' => $written];
            }
            protected function runDdl(string $sql): void
            {
                $this->ddlRuns[] = $sql;
            }
        };
        $service = new class($fakeArtifact) extends YdSpecCompileService {
            public function __construct(private AiArtifactService $fake) { parent::__construct(); }
            protected function initialize(): void { $this->aiArtifactService = $this->fake; }
            public function fake(): AiArtifactService { return $this->fake; }
        };
        return $service;
    }

    public function testApplyDevMaterializesFilesWithoutTouchingRealDb(): void
    {
        // 1) 准备 spec + 编译 stage
        $specId = 'spec_' . bin2hex(random_bytes(8));
        $specDir = rtrim(root_path(), '/') . '/runtime/ai/specs/' . $specId;
        mkdir($specDir, 0755, true);
        copy(dirname(__DIR__, 2) . '/fixtures/ydspec/appointment.json', $specDir . '/ydspec.json');
        $this->cleanupDirs[] = $specDir;

        // 2) 假 AiArtifactService：runDdl 被记录但不连库
        $service = $this->service();
        $out = $service->compile($specId);

        $tmpRoot = sys_get_temp_dir() . '/ydapply_' . bin2hex(random_bytes(4));
        mkdir($tmpRoot, 0755, true);
        $this->cleanupDirs[] = $tmpRoot;

        $res = $service->applyDev($specId, $out['stage_id'], $tmpRoot);

        $this->assertTrue($res['ddl_applied']);
        $this->assertContains('app/model/appointment/Appointment.php', $res['written']);
        $this->assertFileExists($tmpRoot . '/server/app/model/appointment/Appointment.php');
        $ddlRuns = $service->fake()->ddlRuns;
        $this->assertNotEmpty($ddlRuns);
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS `appointments`', $ddlRuns[0]);
    }

    /**
     * 证明 applyDev 把实际应用完全委托给 AiArtifactService::applyArtifact()（门禁），
     * 而不是自己直接物化：门禁拒绝（模拟 checked_failed 工件）时，applyDev 必须原样抛出。
     */
    public function testApplyDevPropagatesGateRejection(): void
    {
        $specId = 'spec_' . bin2hex(random_bytes(8));
        $specDir = rtrim(root_path(), '/') . '/runtime/ai/specs/' . $specId;
        mkdir($specDir, 0755, true);
        copy(dirname(__DIR__, 2) . '/fixtures/ydspec/appointment.json', $specDir . '/ydspec.json');
        $this->cleanupDirs[] = $specDir;

        $fakeArtifact = new class extends AiArtifactService {
            public function __construct() {}
            public function record(string $specId, string $stageId, string $module, string $title): int
            {
                return 7;
            }
            public function runChecks(int $artifactId): array
            {
                return ['artifact_id' => $artifactId, 'state' => 'checked_failed', 'check_summary' => ['passed' => false, 'error_count' => 1, 'warning_count' => 0, 'skipped' => [], 'results' => []]];
            }
            public function findByStage(string $specId, string $stageId): ?array
            {
                // 返回一个可信的 artifact id，确保控制流真正到达 applyArtifact()
                return ['id' => 7, 'spec_id' => $specId, 'stage_id' => $stageId];
            }
            public function applyArtifact(int $artifactId, ?string $projectRootOverride = null): array
            {
                throw new \core\exception\BusinessException('门禁未通过，当前状态：checked_failed');
            }
        };
        $service = new class($fakeArtifact) extends YdSpecCompileService {
            public function __construct(private AiArtifactService $fake) { parent::__construct(); }
            protected function initialize(): void { $this->aiArtifactService = $this->fake; }
        };

        $out = $service->compile($specId);

        $this->expectException(\core\exception\BusinessException::class);
        $service->applyDev($specId, $out['stage_id']);
    }

    public function testApplyDevRejectsBadStageId(): void
    {
        $this->expectException(\core\exception\BusinessException::class);
        $this->service()->applyDev('spec_' . str_repeat('a', 16), 'not-a-stage');
    }

    public function testApplyDevThrowsWhenDdlMissing(): void
    {
        $specId = 'spec_' . bin2hex(random_bytes(8));
        $specDir = rtrim(root_path(), '/') . '/runtime/ai/specs/' . $specId;
        mkdir($specDir, 0755, true);
        copy(dirname(__DIR__, 2) . '/fixtures/ydspec/appointment.json', $specDir . '/ydspec.json');
        $this->cleanupDirs[] = $specDir;

        $service = $this->service();
        $out = $service->compile($specId);
        unlink($specDir . '/' . $out['stage_id'] . '/update.sql');

        $tmpRoot = sys_get_temp_dir() . '/ydapply_' . bin2hex(random_bytes(4));
        mkdir($tmpRoot, 0755, true);
        $this->cleanupDirs[] = $tmpRoot;

        $this->expectException(\core\exception\BusinessException::class);
        $service->applyDev($specId, $out['stage_id'], $tmpRoot);
    }
}
