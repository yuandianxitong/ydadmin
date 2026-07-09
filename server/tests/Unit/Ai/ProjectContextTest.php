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
        $this->assertFileExists($home . '/.ydadmin/projects.json');
    }
}
