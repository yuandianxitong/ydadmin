<?php
// tests/Unit/YdSpec/CheckRunnerTest.php
declare(strict_types=1);

namespace tests\Unit\YdSpec;

use core\ai\checks\CheckContext;
use core\ai\checks\CheckInterface;
use core\ai\checks\CheckResult;
use core\ai\checks\CheckRunner;
use core\ai\checks\PhpLintCheck;
use tests\TestCase;

class CheckRunnerTest extends TestCase
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

    private function makeStage(array $files): CheckContext
    {
        $dir = sys_get_temp_dir() . '/ydchk_' . bin2hex(random_bytes(4));
        mkdir($dir . '/files', 0755, true);
        $this->cleanupDirs[] = $dir;
        $manifestFiles = [];
        foreach ($files as $rel => $content) {
            $abs = $dir . '/files/' . $rel;
            if (!is_dir(dirname($abs))) { mkdir(dirname($abs), 0755, true); }
            file_put_contents($abs, $content);
            $manifestFiles[] = ['path' => $rel, 'bytes' => strlen($content)];
        }
        $manifest = ['files' => $manifestFiles, 'entities' => []];
        return new CheckContext($dir, $manifest, [], '', '', []);
    }

    public function testAggregatesErrorWarningSkipped(): void
    {
        $ctx = $this->makeStage([]);
        $fake = new class implements CheckInterface {
            public function name(): string { return 'fake'; }
            public function check(CheckContext $ctx): array
            {
                return [
                    new CheckResult('fake', 'error', 'boom'),
                    new CheckResult('fake', 'warning', 'meh'),
                    new CheckResult('fake', 'skipped', 'skip'),
                ];
            }
        };
        $summary = (new CheckRunner([$fake]))->run($ctx);
        $this->assertFalse($summary['passed']);
        $this->assertSame(1, $summary['error_count']);
        $this->assertSame(1, $summary['warning_count']);
        $this->assertSame(['fake'], $summary['skipped']);
        $this->assertCount(3, $summary['results']);
    }

    public function testCheckExceptionBecomesError(): void
    {
        $ctx = $this->makeStage([]);
        $boom = new class implements CheckInterface {
            public function name(): string { return 'boom'; }
            public function check(CheckContext $ctx): array { throw new \RuntimeException('kaboom'); }
        };
        $summary = (new CheckRunner([$boom]))->run($ctx);
        $this->assertFalse($summary['passed']);
        $this->assertSame(1, $summary['error_count']);
        $this->assertStringContainsString('kaboom', $summary['results'][0]['message']);
    }

    public function testPhpLintPassesCleanAndFailsBroken(): void
    {
        $good = $this->makeStage(['app/model/x/X.php' => "<?php\nclass X {}\n"]);
        $summary = (new CheckRunner([new PhpLintCheck()]))->run($good);
        $this->assertTrue($summary['passed']);

        $bad = $this->makeStage(['app/model/x/Y.php' => "<?php\nclass Y { function ( }\n"]);
        $summary = (new CheckRunner([new PhpLintCheck()]))->run($bad);
        $this->assertFalse($summary['passed']);
        $this->assertSame('php_lint', $summary['results'][0]['check']);
    }
}
