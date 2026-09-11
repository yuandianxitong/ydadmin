<?php

declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use support\Context;
use Webman\Route;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // 模拟 webman 的请求边界：每个用例拿到干净的 Context
        Context::destroy();
    }

    protected function tearDown(): void
    {
        Context::destroy();
        parent::tearDown();
    }

    /**
     * 进程内只加载一次路由表。Route::load() 用 require_once 引入 config/route.php，
     * 第二次调用会清空路由表却不重新执行文件，所以必须用这个共享门闩。
     */
    protected static function ensureRoutesLoaded(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        Route::load([config_path()]);
        $loaded = true;
    }
}
