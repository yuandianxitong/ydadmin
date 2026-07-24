<?php
// tests/Feature/Ai/YdSpecCompileE2ETest.php
declare(strict_types=1);

namespace tests\Feature\Ai;

use app\service\system\YdSpecCompileService;
use tests\TestCase;

class YdSpecCompileE2ETest extends TestCase
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

    public function testMultiEntityGeneratesBothModulesUnderOneDir(): void
    {
        $specId = $this->seed('order_with_detail');
        $out = (new YdSpecCompileService())->compile($specId);
        $paths = array_column($out['files'], 'path');

        $this->assertContains('app/model/order/Order.php', $paths);
        $this->assertContains('app/model/order/OrderDetail.php', $paths);
        $this->assertContains('app/adminapi/controller/v1/order/OrderController.php', $paths);
        $this->assertContains('app/adminapi/controller/v1/order/OrderDetailController.php', $paths);
        $this->assertContains('app/adminapi/route/order.php', $paths);
        $this->assertContains('admin/src/api/order.ts', $paths);
        $this->assertContains('admin/src/api/order-detail.ts', $paths);
    }

    public function testGeneratedPhpParsesForEachEntity(): void
    {
        $specId = $this->seed('appointment');
        $out = (new YdSpecCompileService())->compile($specId);
        $stageDir = rtrim(root_path(), '/') . '/' . $out['dir'] . '/files';

        foreach ($out['files'] as $f) {
            if (!str_ends_with($f['path'], '.php')) { continue; }
            $file = $stageDir . '/' . $f['path'];
            $result = shell_exec('php -l ' . escapeshellarg($file) . ' 2>&1');
            $this->assertStringContainsString('No syntax errors detected', (string) $result, $f['path'] . ' 语法错误：' . $result);
        }
    }
}
