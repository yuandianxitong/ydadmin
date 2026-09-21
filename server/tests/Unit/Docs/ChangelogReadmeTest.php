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

    public function test_readme_marks_m8_done_and_documents_compose_and_zip(): void
    {
        $path = dirname(__DIR__, 4) . '/README.md';
        $text = (string) file_get_contents($path);
        $this->assertStringContainsString('✅（安装向导 + yd:update + Docker compose + 发布包）', $text);
        $this->assertStringContainsString('docker compose up -d --build', $text);
        $this->assertStringContainsString('scripts/release.sh', $text);
        $this->assertStringContainsString('ydadmin-2.0.0.zip', $text);
        $this->assertStringNotContainsString('进行中（安装向导 + yd:update；Docker / 发布包另开）', $text);
    }
}
