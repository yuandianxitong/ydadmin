<?php
// tests/Feature/Ai/YdSpecApplyDevTest.php
declare(strict_types=1);

namespace tests\Feature\Ai;

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

    public function testApplyDevMaterializesFilesWithoutTouchingRealDb(): void
    {
        // 1) 准备 spec + 编译 stage
        $specId = 'spec_' . bin2hex(random_bytes(8));
        $specDir = rtrim(root_path(), '/') . '/runtime/ai/specs/' . $specId;
        mkdir($specDir, 0755, true);
        copy(dirname(__DIR__, 2) . '/fixtures/ydspec/appointment.json', $specDir . '/ydspec.json');
        $this->cleanupDirs[] = $specDir;

        // 2) 覆盖 runDdl，记录被调用但不连库
        $service = new class extends YdSpecCompileService {
            public string $ranDdl = '';
            protected function runDdl(string $sql): void { $this->ranDdl = $sql; }
        };
        $out = $service->compile($specId);

        $tmpRoot = sys_get_temp_dir() . '/ydapply_' . bin2hex(random_bytes(4));
        mkdir($tmpRoot, 0755, true);
        $this->cleanupDirs[] = $tmpRoot;

        $res = $service->applyDev($specId, $out['stage_id'], $tmpRoot);

        $this->assertTrue($res['ddl_applied']);
        $this->assertContains('app/model/appointment/Appointment.php', $res['written']);
        $this->assertFileExists($tmpRoot . '/server/app/model/appointment/Appointment.php');
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS `appointments`', $service->ranDdl);
    }

    public function testApplyDevRejectsBadStageId(): void
    {
        $this->expectException(\core\exception\BusinessException::class);
        (new YdSpecCompileService())->applyDev('spec_' . str_repeat('a', 16), 'not-a-stage');
    }

    public function testApplyDevThrowsWhenDdlMissing(): void
    {
        $specId = 'spec_' . bin2hex(random_bytes(8));
        $specDir = rtrim(root_path(), '/') . '/runtime/ai/specs/' . $specId;
        mkdir($specDir, 0755, true);
        copy(dirname(__DIR__, 2) . '/fixtures/ydspec/appointment.json', $specDir . '/ydspec.json');
        $this->cleanupDirs[] = $specDir;

        $service = new class extends YdSpecCompileService {
            protected function runDdl(string $sql): void {}
        };
        $out = $service->compile($specId);
        unlink($specDir . '/' . $out['stage_id'] . '/update.sql');

        $tmpRoot = sys_get_temp_dir() . '/ydapply_' . bin2hex(random_bytes(4));
        mkdir($tmpRoot, 0755, true);
        $this->cleanupDirs[] = $tmpRoot;

        $this->expectException(\core\exception\BusinessException::class);
        $service->applyDev($specId, $out['stage_id'], $tmpRoot);
    }
}
