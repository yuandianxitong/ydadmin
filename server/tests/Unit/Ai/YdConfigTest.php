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
    }
}
