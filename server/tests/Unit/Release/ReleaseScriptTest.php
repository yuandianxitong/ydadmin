<?php

declare(strict_types=1);

namespace tests\Unit\Release;

use tests\TestCase;

final class ReleaseScriptTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        parent::setUp();
        $this->source = sys_get_temp_dir() . '/yd-rel-' . bin2hex(random_bytes(4));
        mkdir($this->source . '/config', 0o755, true);
        mkdir($this->source . '/public/admin', 0o755, true);
        mkdir($this->source . '/app', 0o755, true);
        mkdir($this->source . '/vendor', 0o755, true);
        mkdir($this->source . '/tests', 0o755, true);
        mkdir($this->source . '/database/generated', 0o755, true);
        mkdir($this->source . '/runtime', 0o755, true);
        file_put_contents($this->source . '/config/version.php', "<?php\nreturn ['version' => '2.0.0'];\n");
        file_put_contents($this->source . '/public/admin/index.html', '<html></html>');
        file_put_contents($this->source . '/app/keep.php', '<?php');
        file_put_contents($this->source . '/composer.json', '{}');
        file_put_contents($this->source . '/start.php', '<?php');
        file_put_contents($this->source . '/.env', 'DB_PASSWORD=secret');
        file_put_contents($this->source . '/.env.example', 'DB_HOST = 127.0.0.1');
        file_put_contents($this->source . '/vendor/autoload.php', '<?php');
        file_put_contents($this->source . '/tests/Nope.php', '<?php');
        file_put_contents($this->source . '/database/generated/x-menu.sql', 'SELECT 1');
    }

    protected function tearDown(): void
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->source);
        parent::tearDown();
    }

    public function test_list_excludes_secrets_and_vendor(): void
    {
        $script = dirname(__DIR__, 4) . '/scripts/release.sh';
        $this->assertFileExists($script);
        $cmd = 'sh ' . escapeshellarg($script)
            . ' --list --skip-build --source ' . escapeshellarg($this->source);
        exec($cmd . ' 2>&1', $out, $code);
        $this->assertSame(0, $code, implode("\n", $out));
        $list = implode("\n", $out);
        $this->assertStringContainsString('ydadmin-2.0.0/server/config/version.php', $list);
        $this->assertStringContainsString('ydadmin-2.0.0/server/public/admin/index.html', $list);
        $this->assertStringContainsString('ydadmin-2.0.0/server/.env.example', $list);
        $this->assertStringContainsString('ydadmin-2.0.0/server/app/keep.php', $list);
        $this->assertStringNotContainsString('/vendor/', $list);
        $this->assertStringNotContainsString('.env\n', $list . "\n");
        $this->assertStringNotContainsString('/.env' . "\n", $list . "\n");
        $this->assertStringNotContainsString('ydadmin-2.0.0/server/.env' . "\n", $list . "\n");
        $this->assertStringNotContainsString('/tests/', $list);
        $this->assertStringNotContainsString('generated/', $list);
        $this->assertStringNotContainsString('DB_PASSWORD', $list);
    }
}
