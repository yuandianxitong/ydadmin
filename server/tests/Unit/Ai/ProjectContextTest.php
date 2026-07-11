<?php
// server/tests/Unit/Ai/ProjectContextTest.php
namespace tests\Unit\Ai;

use core\ai\ProjectContext;
use tests\TestCase;

class ProjectContextTest extends TestCase
{
    public function testIdIsStableAcrossInstances(): void
    {
        $home = sys_get_temp_dir() . '/ydai_test_' . uniqid();
        mkdir($home, 0700, true);
        $a = (new ProjectContext($home))->id();
        $b = (new ProjectContext($home))->id();
        $this->assertSame($a, $b);
        $this->assertStringStartsWith('proj_', $a);
        $this->assertFileExists($home . '/.ydsaas/projects.json');
    }

    public function testFallsBackToLegacyYdadminDir(): void
    {
        $home = sys_get_temp_dir() . '/ydai_test_' . uniqid();
        mkdir($home . '/.ydadmin', 0700, true);
        $legacyId = 'proj_' . bin2hex(random_bytes(8));
        file_put_contents(
            $home . '/.ydadmin/projects.json',
            json_encode([root_path() => $legacyId], JSON_UNESCAPED_SLASHES)
        );
        $this->assertSame($legacyId, (new ProjectContext($home))->id());
        // 只读回退：不应产生新目录写入
        $this->assertFileDoesNotExist($home . '/.ydsaas/projects.json');
    }
}
