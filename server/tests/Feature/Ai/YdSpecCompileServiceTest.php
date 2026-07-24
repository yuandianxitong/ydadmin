<?php
// tests/Feature/Ai/YdSpecCompileServiceTest.php
declare(strict_types=1);

namespace tests\Feature\Ai;

use app\service\system\YdSpecCompileService;
use tests\TestCase;

class YdSpecCompileServiceTest extends TestCase
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

    private function seedSpec(string $fixture): string
    {
        $specId = 'spec_' . bin2hex(random_bytes(8));
        $dir = rtrim(root_path(), '/') . '/runtime/ai/specs/' . $specId;
        mkdir($dir, 0755, true);
        copy(dirname(__DIR__, 2) . "/fixtures/ydspec/{$fixture}.json", $dir . '/ydspec.json');
        $this->cleanupDirs[] = $dir;
        return $specId;
    }

    public function testCompileWritesStageArtifacts(): void
    {
        $specId = $this->seedSpec('appointment');
        $out = (new YdSpecCompileService())->compile($specId);

        $stageDir = rtrim(root_path(), '/') . '/' . $out['dir'];
        $this->assertMatchesRegularExpression('/^compile_[0-9a-f]{16}$/', $out['stage_id']);
        $this->assertFileExists($stageDir . '/schema_patch.sql');
        $this->assertFileExists($stageDir . '/update.sql');
        $this->assertFileExists($stageDir . '/manifest.json');
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS `appointments`', $out['schema_patch']);

        $paths = array_column($out['files'], 'path');
        $this->assertContains('app/model/appointment/Appointment.php', $paths);
        $this->assertContains('app/adminapi/route/appointment.php', $paths);
        $this->assertFileExists($stageDir . '/files/app/model/appointment/Appointment.php');
    }

    public function testCompiledRouteFileIsSingleLevelWithMiddleware(): void
    {
        $specId = $this->seedSpec('order_with_detail');
        $out = (new YdSpecCompileService())->compile($specId);
        $routeFile = rtrim(root_path(), '/') . '/' . $out['dir'] . '/files/app/adminapi/route/order.php';
        $route = (string) file_get_contents($routeFile);

        $this->assertStringContainsString("Route::group('order', function ()", $route);
        $this->assertStringContainsString("Route::group('order-detail', function ()", $route);
        $this->assertStringContainsString("'v1.order.OrderController/index'", $route);
        $this->assertStringContainsString("'v1.order.OrderDetailController/index'", $route);
        $this->assertStringContainsString("->middleware(['admin_auth', 'admin_permission', 'admin_log'])", $route);
        // 不得出现双层嵌套的 module 组
        $this->assertStringNotContainsString("Route::group('order', function () {\n\n    Route::group(", $route);
    }

    public function testCompileRejectsUnknownSpec(): void
    {
        $this->expectException(\core\exception\BusinessException::class);
        (new YdSpecCompileService())->compile('spec_' . str_repeat('0', 16));
    }

    public function testRecompileCreatesDistinctStages(): void
    {
        $specId = $this->seedSpec('appointment');
        $svc = new YdSpecCompileService();
        $a = $svc->compile($specId);
        $b = $svc->compile($specId);
        $this->assertNotSame($a['stage_id'], $b['stage_id']);
        $base = rtrim(root_path(), '/') . '/runtime/ai/specs/' . $specId;
        $this->assertDirectoryExists($base . '/' . $a['stage_id']);
        $this->assertDirectoryExists($base . '/' . $b['stage_id']);
    }
}
