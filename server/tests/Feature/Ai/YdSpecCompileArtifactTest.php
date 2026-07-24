<?php
// tests/Feature/Ai/YdSpecCompileArtifactTest.php
declare(strict_types=1);

namespace tests\Feature\Ai;

use app\service\system\AiArtifactService;
use app\service\system\YdSpecCompileService;
use tests\TestCase;

class YdSpecCompileArtifactTest extends TestCase
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

    private function seedSpec(string $fixture): string
    {
        $specId = 'spec_' . bin2hex(random_bytes(8));
        $dir = rtrim(root_path(), '/') . '/runtime/ai/specs/' . $specId;
        mkdir($dir, 0755, true);
        copy(dirname(__DIR__, 2) . "/fixtures/ydspec/{$fixture}.json", $dir . '/ydspec.json');
        $this->cleanupDirs[] = $dir;
        return $specId;
    }

    private function service(): YdSpecCompileService
    {
        // 假 AiArtifactService：record 返回固定 id 并记录调用，runChecks 返回 canned summary，不连库
        $fakeArtifact = new class extends AiArtifactService {
            public array $calls = [];
            public function __construct() {}
            public function record(string $specId, string $stageId, string $module, string $title): int
            {
                $this->calls[] = ['record', $specId, $stageId, $module, $title];
                return 42;
            }
            public function runChecks(int $artifactId): array
            {
                $this->calls[] = ['runChecks', $artifactId];
                return ['artifact_id' => $artifactId, 'state' => 'checked_passed', 'check_summary' => ['passed' => true, 'error_count' => 0, 'warning_count' => 0, 'skipped' => [], 'results' => []]];
            }
        };
        return new class($fakeArtifact) extends YdSpecCompileService {
            public function __construct(private AiArtifactService $fake) { parent::__construct(); }
            protected function initialize(): void { $this->aiArtifactService = $this->fake; }
        };
    }

    public function testCompileRecordsArtifactAndRunsChecks(): void
    {
        $specId = $this->seedSpec('appointment');
        $out = $this->service()->compile($specId);

        $this->assertSame(42, $out['artifact_id']);
        $this->assertTrue($out['check_summary']['passed']);

        // manifest 含 entities，且包含检查库消费的全部 7 个契约字段
        $manifestFile = rtrim(root_path(), '/') . '/' . $out['dir'] . '/manifest.json';
        $manifest = json_decode((string) file_get_contents($manifestFile), true);
        $this->assertNotEmpty($manifest['entities']);
        $entity = $manifest['entities'][0];
        $this->assertSame('appointments', $entity['table']);
        foreach (['name', 'table', 'module', 'model', 'route_group', 'is_main', 'has_status_switch'] as $field) {
            $this->assertArrayHasKey($field, $entity, "manifest entities[0] 缺少契约字段：{$field}");
        }
    }
}
