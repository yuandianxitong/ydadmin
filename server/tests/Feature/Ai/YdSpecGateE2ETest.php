<?php
// tests/Feature/Ai/YdSpecGateE2ETest.php
declare(strict_types=1);

namespace tests\Feature\Ai;

use app\service\system\AiArtifactService;
use app\service\system\YdSpecCompileService;
use core\ai\checks\CheckContext;
use core\ai\checks\CheckRunner;
use core\ai\checks\ForbiddenPatternsCheck;
use core\ai\checks\LayerComplianceCheck;
use core\ai\checks\PathConventionCheck;
use core\ai\checks\PhpLintCheck;
use core\ai\checks\RequiredFilesCheck;
use core\ai\checks\RouteLoadingCheck;
use tests\TestCase;

class YdSpecGateE2ETest extends TestCase
{
    private array $cleanupDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->cleanupDirs as $dir) {
            if (!is_dir($dir)) { continue; }
            $items = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($items as $item) {
                $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }
            rmdir($dir);
        }
        parent::tearDown();
    }

    private function seed(string $fixture): string
    {
        $specId = 'spec_' . bin2hex(random_bytes(8));
        $dir = rtrim(root_path(), '/') . '/runtime/ai/specs/' . $specId;
        mkdir($dir, 0755, true);
        copy(dirname(__DIR__, 2) . "/fixtures/ydspec/{$fixture}.json", $dir . '/ydspec.json');
        $this->cleanupDirs[] = $dir;
        return $specId;
    }

    /** 用注入假 AiArtifactService 的 compile 产出真实 stage（不落库） */
    private function compileService(): YdSpecCompileService
    {
        $fake = new class extends AiArtifactService {
            public function __construct() {}
            public function record(string $specId, string $stageId, string $module, string $title): int { return 1; }
            public function runChecks(int $artifactId): array { return ['artifact_id' => 1, 'state' => 'checked_passed', 'check_summary' => ['passed' => true]]; }
        };
        return new class($fake) extends YdSpecCompileService {
            public function __construct(private AiArtifactService $f) { parent::__construct(); }
            protected function initialize(): void { $this->aiArtifactService = $this->f; }
        };
    }

    public function testGoodArtifactPassesStaticChecks(): void
    {
        $specId = $this->seed('order_with_detail');
        $out = $this->compileService()->compile($specId);
        $dir = rtrim(root_path(), '/') . '/' . $out['dir'];
        $manifest = json_decode((string) file_get_contents($dir . '/manifest.json'), true);
        $spec = json_decode((string) file_get_contents(rtrim(root_path(), '/') . '/runtime/ai/specs/' . $specId . '/ydspec.json'), true);
        $ctx = new CheckContext($dir, $manifest, $manifest['entities'], (string) file_get_contents($dir . '/schema_patch.sql'), (string) file_get_contents($dir . '/update.sql'), $spec);

        // 不含 DDL 检查（无库），只跑静态五件 + 路由加载
        $runner = new CheckRunner([
            new PhpLintCheck(),
            new RequiredFilesCheck(),
            new LayerComplianceCheck(),
            new PathConventionCheck(),
            new ForbiddenPatternsCheck(),
            new RouteLoadingCheck(),
        ]);
        $summary = $runner->run($ctx);
        $this->assertTrue($summary['passed'], '静态检查应全过，实际：' . json_encode($summary['results'], JSON_UNESCAPED_UNICODE));
    }
}
