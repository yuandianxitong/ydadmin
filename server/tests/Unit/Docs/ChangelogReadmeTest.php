<?php

declare(strict_types=1);

namespace tests\Unit\Docs;

use tests\TestCase;

final class ChangelogReadmeTest extends TestCase
{
    public function test_changelog_has_milestone_headings(): void
    {
        $path = dirname(__DIR__, 4) . '/CHANGELOG.md';
        $this->assertFileExists($path);
        $text = (string) file_get_contents($path);
        foreach (['M0', 'M1', 'M2', 'M3', 'M4', 'M5', 'M6', 'M7', 'M8'] as $m) {
            $this->assertStringContainsString("## [{$m}]", $text);
        }
        $this->assertStringContainsString('yd:update', $text);
        $this->assertStringContainsString('docker compose', $text);
        $this->assertStringContainsString('release.sh', $text);
    }

    public function test_readme_introduces_the_open_source_product(): void
    {
        $path = dirname(__DIR__, 4) . '/README.md';
        $text = (string) file_get_contents($path);
        $this->assertStringContainsString('https://www.dev007.cn/oss/logo.png', $text);
        $this->assertStringContainsString('<h1 align="center">元点Admin</h1>', $text);
        $this->assertStringContainsString('https://admin.dev007.cn', $text);
        $this->assertStringContainsString('webman', $text);
    }
}
