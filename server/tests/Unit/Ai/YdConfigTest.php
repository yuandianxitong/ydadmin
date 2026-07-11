<?php
// server/tests/Unit/Ai/YdConfigTest.php
namespace tests\Unit\Ai;

use core\ai\YdConfig;
use tests\TestCase;

class YdConfigTest extends TestCase
{
    public function testSetGetAndPermission(): void
    {
        $home = sys_get_temp_dir() . '/ydai_cfg_' . uniqid();
        mkdir($home, 0700, true);
        $cfg = new YdConfig($home);
        $cfg->set('token', 'yd_test_abc');
        $this->assertSame('yd_test_abc', (new YdConfig($home))->get('token'));
        $this->assertSame('0600', substr(sprintf('%o', fileperms($cfg->path())), -4));
        $this->assertNull($cfg->get('feedback_optin'));
        $this->assertStringContainsString('/.ydsaas/config.json', $cfg->path());
    }

    public function testReadsLegacyYdadminConfigAndMigratesOnWrite(): void
    {
        $home = sys_get_temp_dir() . '/ydai_cfg_' . uniqid();
        mkdir($home . '/.ydadmin', 0700, true);
        file_put_contents($home . '/.ydadmin/config.json', json_encode(['token' => 'yd_legacy_token']));

        // 旧目录只读回退
        $cfg = new YdConfig($home);
        $this->assertSame('yd_legacy_token', $cfg->get('token'));
        $this->assertFileDoesNotExist($home . '/.ydsaas/config.json');

        // 首次写入时整体迁移到新目录，旧值保留
        $cfg->set('endpoint', 'http://127.0.0.1:8000');
        $this->assertFileExists($home . '/.ydsaas/config.json');
        $fresh = new YdConfig($home);
        $this->assertSame('yd_legacy_token', $fresh->get('token'));
        $this->assertSame('http://127.0.0.1:8000', $fresh->get('endpoint'));
    }
}
