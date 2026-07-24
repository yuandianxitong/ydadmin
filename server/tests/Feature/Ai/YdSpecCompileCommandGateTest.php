<?php
// tests/Feature/Ai/YdSpecCompileCommandGateTest.php
declare(strict_types=1);

namespace tests\Feature\Ai;

use app\command\YdSpecCompileCommand;
use app\service\system\YdSpecCompileService;
use tests\TestCase;
use think\console\Input;
use think\console\Output;
use think\facade\Console;

class YdSpecCompileCommandGateTest extends TestCase
{
    private string $specDir = '';

    protected function tearDown(): void
    {
        if ($this->specDir !== '' && is_dir($this->specDir)) {
            $items = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->specDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($items as $item) {
                $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }
            rmdir($this->specDir);
        }
        parent::tearDown();
    }

    public function testCompilePrintsCheckSummary(): void
    {
        $specId = 'spec_' . bin2hex(random_bytes(8));
        $this->specDir = rtrim(root_path(), '/') . '/runtime/ai/specs/' . $specId;
        mkdir($this->specDir, 0755, true);
        copy(dirname(__DIR__, 2) . '/fixtures/ydspec/appointment.json', $this->specDir . '/ydspec.json');

        $output = Console::call('ydspec:compile', [$specId])->fetch();

        $this->assertStringContainsString('检查', $output);
        $this->assertStringContainsString('app/model/appointment/Appointment.php', $output);
    }

    public function testApplyRefusedWhenChecksFail(): void
    {
        $fakeService = new class extends YdSpecCompileService {
            public bool $applied = false;

            public function __construct()
            {
            }

            public function compile(string $specId): array
            {
                return [
                    'artifact_id'   => 1,
                    'stage_id'      => 'compile_' . bin2hex(random_bytes(8)),
                    'dir'           => 'runtime/ai/specs/fake/compile_fake',
                    'files'         => [],
                    'check_summary' => [
                        'passed'        => false,
                        'error_count'   => 1,
                        'warning_count' => 0,
                        'skipped'       => [],
                        'results'       => [
                            ['check' => 'php_lint', 'severity' => 'error', 'message' => 'boom', 'ref' => 'x/Y.php'],
                        ],
                    ],
                ];
            }

            public function applyDev(string $specId, string $stageId, ?string $projectRootOverride = null): array
            {
                $this->applied = true;
                return [];
            }
        };

        $command = new class ($fakeService) extends YdSpecCompileCommand {
            public function __construct(private YdSpecCompileService $fakeService)
            {
                parent::__construct();
            }

            protected function makeService(): YdSpecCompileService
            {
                return $this->fakeService;
            }
        };

        $input = new Input(['spec_fake', '--apply']);
        $input->bind($command->getDefinition());
        $output = new Output('buffer');

        $exitCode = $command->run($input, $output);
        $text = $output->fetch();

        $this->assertSame(1, $exitCode);
        $this->assertFalse($fakeService->applied, 'applyDev must NOT be called when checks fail');
        $this->assertStringContainsString('门禁未通过，拒绝应用', $text);
    }
}
