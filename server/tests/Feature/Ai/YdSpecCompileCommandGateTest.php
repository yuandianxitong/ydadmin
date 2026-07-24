<?php
// tests/Feature/Ai/YdSpecCompileCommandGateTest.php
declare(strict_types=1);

namespace tests\Feature\Ai;

use tests\TestCase;
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
}
