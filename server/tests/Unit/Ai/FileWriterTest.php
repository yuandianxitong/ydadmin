<?php
// server/tests/Unit/Ai/FileWriterTest.php
namespace tests\Unit\Ai;

use core\ai\FileWriter;
use tests\TestCase;

class FileWriterTest extends TestCase
{
    private function root(): string
    {
        $d = sys_get_temp_dir() . '/ydai_fw_' . uniqid();
        mkdir($d . '/runtime', 0755, true);
        return $d;
    }

    public function testIsSafeRelPath(): void
    {
        $this->assertTrue(FileWriter::isSafeRelPath('server/app/service/a/AService.php'));
        $this->assertFalse(FileWriter::isSafeRelPath('/etc/passwd'));
        $this->assertFalse(FileWriter::isSafeRelPath('a/../../x.php'));
        $this->assertFalse(FileWriter::isSafeRelPath(''));
        $this->assertFalse(FileWriter::isSafeRelPath('\\evil\\file.php'));
        $this->assertFalse(FileWriter::isSafeRelPath('C:\\Windows\\evil.php'));
    }

    public function testStageAndCommit(): void
    {
        $root = $this->root();
        $files = [
            ['path' => 'server/app/service/demo/DemoService.php', 'code' => '<?php // demo'],
            ['path' => '/abs/evil.php', 'code' => 'x'],
        ];
        $writer = new FileWriter($root);
        $temp = $writer->stageToTemp($files);
        $this->assertFileExists($temp . '/server/app/service/demo/DemoService.php');
        $this->assertSame(['/abs/evil.php'], $writer->getSkipped());

        $written = $writer->commit($temp, $files);
        $this->assertSame(['server/app/service/demo/DemoService.php'], $written);
        $this->assertFileExists($root . '/server/app/service/demo/DemoService.php');
    }
}
