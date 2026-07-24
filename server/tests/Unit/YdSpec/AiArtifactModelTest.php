<?php
// tests/Unit/YdSpec/AiArtifactModelTest.php
declare(strict_types=1);

namespace tests\Unit\YdSpec;

use app\model\ai\AiArtifact;
use tests\TestCase;

class AiArtifactModelTest extends TestCase
{
    public function testTableNameAndJsonCasts(): void
    {
        $m = new AiArtifact();
        $this->assertSame('ai_artifacts', $m->getName());
        $type = (new \ReflectionClass($m))->getProperty('type');
        $type->setAccessible(true);
        $casts = $type->getValue($m);
        $this->assertSame('json', $casts['check_summary']);
        $this->assertSame('json', $casts['applied_files']);
    }
}
