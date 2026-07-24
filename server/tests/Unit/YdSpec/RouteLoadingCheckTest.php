<?php
// tests/Unit/YdSpec/RouteLoadingCheckTest.php
declare(strict_types=1);

namespace tests\Unit\YdSpec;

use core\ai\checks\CheckContext;
use core\ai\checks\RouteLoadingCheck;
use tests\TestCase;

class RouteLoadingCheckTest extends TestCase
{
    private array $cleanupDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->cleanupDirs as $dir) {
            if (!is_dir($dir)) { continue; }
            $items = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($items as $item) {
                $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }
            rmdir($dir);
        }
        parent::tearDown();
    }

    private function ctx(string $routeContent, array $entities, string $module): CheckContext
    {
        $dir = sys_get_temp_dir() . '/ydchk_' . bin2hex(random_bytes(4));
        $rel = "app/adminapi/route/{$module}.php";
        $abs = $dir . '/files/' . $rel;
        mkdir(dirname($abs), 0755, true);
        file_put_contents($abs, $routeContent);
        $this->cleanupDirs[] = $dir;
        return new CheckContext($dir, ['files' => [['path' => $rel, 'bytes' => strlen($routeContent)]], 'entities' => $entities], $entities, '', '', ['module' => ['name' => $module]]);
    }

    public function testValidRoutePasses(): void
    {
        $route = "<?php\nuse think\\facade\\Route;\nRoute::group('widget', function () {\n    Route::get('', 'v1.widget.WidgetController/index');\n})->middleware(['admin_auth', 'admin_permission', 'admin_log']);\n";
        $ctx = $this->ctx($route, [['route_group' => 'widget', 'module' => 'widget', 'model' => 'Widget']], 'widget');
        $this->assertSame([], (new RouteLoadingCheck())->check($ctx));
    }

    public function testBrokenRouteFails(): void
    {
        $route = "<?php\nuse think\\facade\\Route;\nRoute::group('widget', function ( {\n"; // 语法错误
        $ctx = $this->ctx($route, [['route_group' => 'widget', 'module' => 'widget', 'model' => 'Widget']], 'widget');
        $res = (new RouteLoadingCheck())->check($ctx);
        $this->assertNotEmpty($res);
        $this->assertSame('error', $res[0]->severity);
    }

    public function testMissingMiddlewareFails(): void
    {
        $route = "<?php\nuse think\\facade\\Route;\nRoute::group('widget', function () {\n    Route::get('', 'v1.widget.WidgetController/index');\n});\n";
        $ctx = $this->ctx($route, [['route_group' => 'widget', 'module' => 'widget', 'model' => 'Widget']], 'widget');
        $res = (new RouteLoadingCheck())->check($ctx);
        $this->assertNotEmpty($res);
        $this->assertSame('error', $res[0]->severity);
    }

    public function testMissingRouteFileFails(): void
    {
        $module = 'widget';
        $entities = [['route_group' => 'widget', 'module' => $module, 'model' => 'Widget']];
        $dir = sys_get_temp_dir() . '/ydchk_' . bin2hex(random_bytes(4));
        mkdir($dir . '/files', 0755, true);
        $this->cleanupDirs[] = $dir;
        $ctx = new CheckContext($dir, ['files' => [], 'entities' => $entities], $entities, '', '', ['module' => ['name' => $module]]);

        $res = (new RouteLoadingCheck())->check($ctx);
        $this->assertNotEmpty($res);
        $this->assertSame('error', $res[0]->severity);
        $this->assertSame('route_loading', $res[0]->check);
    }
}
