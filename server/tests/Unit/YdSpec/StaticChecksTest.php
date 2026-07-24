<?php
// tests/Unit/YdSpec/StaticChecksTest.php
declare(strict_types=1);

namespace tests\Unit\YdSpec;

use core\ai\checks\CheckContext;
use core\ai\checks\ForbiddenPatternsCheck;
use core\ai\checks\LayerComplianceCheck;
use core\ai\checks\PathConventionCheck;
use core\ai\checks\RequiredFilesCheck;
use tests\TestCase;

class StaticChecksTest extends TestCase
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

    /** @param array<string,string> $files rel => content */
    private function ctx(array $files, array $entities, array $spec = []): CheckContext
    {
        $dir = sys_get_temp_dir() . '/ydchk_' . bin2hex(random_bytes(4));
        mkdir($dir . '/files', 0755, true);
        $this->cleanupDirs[] = $dir;
        $mf = [];
        foreach ($files as $rel => $content) {
            $abs = $dir . '/files/' . $rel;
            if (!is_dir(dirname($abs))) { mkdir(dirname($abs), 0755, true); }
            file_put_contents($abs, $content);
            $mf[] = ['path' => $rel, 'bytes' => strlen($content)];
        }
        return new CheckContext($dir, ['files' => $mf, 'entities' => $entities], $entities, '', '', $spec);
    }

    private function entity(): array
    {
        return ['name' => 'Widget', 'table' => 'widgets', 'module' => 'widget', 'model' => 'Widget', 'route_group' => 'widget', 'is_main' => true, 'has_status_switch' => false];
    }

    private function fullFileSet(): array
    {
        return [
            'app/model/widget/Widget.php'                              => "<?php\nnamespace app\\model\\widget;\nclass Widget {}\n",
            'app/repository/widget/WidgetRepository.php'               => "<?php\nnamespace app\\repository\\widget;\nclass WidgetRepository {}\n",
            'app/service/widget/WidgetService.php'                     => "<?php\nnamespace app\\service\\widget;\nclass WidgetService {}\n",
            'app/adminapi/controller/v1/widget/WidgetController.php'   => "<?php\nnamespace app\\adminapi\\controller\\v1\\widget;\nclass WidgetController {}\n",
            'app/adminapi/validate/v1/widget/WidgetValidate.php'       => "<?php\nnamespace app\\adminapi\\validate\\v1\\widget;\nclass WidgetValidate {}\n",
            'admin/src/api/widget.ts'                                  => "export const x = 1\n",
            'app/adminapi/route/widget.php'                            => "<?php\nuse think\\facade\\Route;\nRoute::group('widget', function () {\n    Route::get('', 'v1.widget.WidgetController/index');\n})->middleware(['admin_auth', 'admin_permission', 'admin_log']);\n",
        ];
    }

    public function testRequiredFilesPassesWhenComplete(): void
    {
        $ctx = $this->ctx($this->fullFileSet(), [$this->entity()], ['module' => ['name' => 'widget']]);
        $this->assertSame([], (new RequiredFilesCheck())->check($ctx));
    }

    public function testRequiredFilesFailsWhenMissing(): void
    {
        $files = $this->fullFileSet();
        unset($files['app/service/widget/WidgetService.php']);
        $ctx = $this->ctx($files, [$this->entity()], ['module' => ['name' => 'widget']]);
        $res = (new RequiredFilesCheck())->check($ctx);
        $this->assertNotEmpty($res);
        $this->assertSame('error', $res[0]->severity);
    }

    public function testLayerComplianceFlagsDbInService(): void
    {
        $files = $this->fullFileSet();
        $files['app/service/widget/WidgetService.php'] = "<?php\nnamespace app\\service\\widget;\nclass WidgetService { function f(){ Db::table('x')->find(); } }\n";
        $ctx = $this->ctx($files, [$this->entity()], ['module' => ['name' => 'widget']]);
        $res = (new LayerComplianceCheck())->check($ctx);
        $this->assertNotEmpty($res);
        $this->assertSame('layer_compliance', $res[0]->check);
    }

    public function testLayerComplianceAllowsTransactionInService(): void
    {
        $files = $this->fullFileSet();
        $files['app/service/widget/WidgetService.php'] = "<?php\nnamespace app\\service\\widget;\nuse think\\facade\\Db;\nclass WidgetService { function f(){ Db::startTrans(); Db::commit(); } }\n";
        $ctx = $this->ctx($files, [$this->entity()], ['module' => ['name' => 'widget']]);
        $this->assertSame([], (new LayerComplianceCheck())->check($ctx));
    }

    public function testPathConventionFlagsWrongNamespace(): void
    {
        $files = $this->fullFileSet();
        $files['app/model/widget/Widget.php'] = "<?php\nnamespace app\\model\\wrong;\nclass Widget {}\n";
        $ctx = $this->ctx($files, [$this->entity()], ['module' => ['name' => 'widget']]);
        $res = (new PathConventionCheck())->check($ctx);
        $this->assertNotEmpty($res);
        $this->assertSame('path_convention', $res[0]->check);
    }

    public function testForbiddenPatternsFlagsOldFieldNames(): void
    {
        $files = $this->fullFileSet();
        $files['app/model/widget/Widget.php'] = "<?php\nnamespace app\\model\\widget;\nclass Widget { protected \$x = 'create_time'; }\n";
        $ctx = $this->ctx($files, [$this->entity()], ['module' => ['name' => 'widget']]);
        $res = (new ForbiddenPatternsCheck())->check($ctx);
        $this->assertNotEmpty($res);
        $this->assertSame('error', $res[0]->severity);
    }

    public function testForbiddenPatternsPlaceholderIsWarning(): void
    {
        $files = $this->fullFileSet();
        $files['admin/src/api/widget.ts'] = "// TODO: 补充选项\nexport const x = 1\n";
        $ctx = $this->ctx($files, [$this->entity()], ['module' => ['name' => 'widget']]);
        $res = (new ForbiddenPatternsCheck())->check($ctx);
        $this->assertNotEmpty($res);
        $this->assertSame('warning', $res[0]->severity);
    }
}
