<?php

declare(strict_types=1);

namespace tests\Pure;

use PHPUnit\Framework\TestCase;

final class PluginKernelBoundaryTest extends TestCase
{
    public function test_copied_kernel_is_a_known_core_module_and_names_no_saas_config(): void
    {
        $core = dirname(__DIR__, 2) . '/core';
        $this->assertDirectoryExists($core . '/plugin');
        $this->assertDirectoryExists($core . '/saga');
        $this->assertFileExists($core . '/diy/NormalizedWidget.php');
        $this->assertFileExists($core . '/runtime/RuntimePaths.php');
        $this->assertFileExists($core . '/support/ErrorClassifier.php');

        $command = escapeshellarg(PHP_BINARY) . ' '
            . escapeshellarg(dirname(__DIR__, 2) . '/scripts/check-core-architecture.php') . ' '
            . escapeshellarg($core);
        exec($command . ' 2>&1', $output, $status);
        $this->assertSame(0, $status, implode("\n", $output));

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($core . '/plugin', \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $text = (string) file_get_contents($file->getPathname());
            $this->assertStringNotContainsString('saas_plugin', $text, $file->getPathname());
            $this->assertStringNotContainsString("config('saas", $text, $file->getPathname());
        }
    }
}
