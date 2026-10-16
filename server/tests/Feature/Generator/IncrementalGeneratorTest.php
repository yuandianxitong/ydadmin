<?php

declare(strict_types=1);

namespace tests\Feature\Generator;

use core\exception\BusinessException;
use core\generator\GeneratorRequest;
use core\module\ModuleCatalog;
use support\Container;
use support\Db;
use tests\Support\GeneratorFixture;
use tests\Support\TestableGeneratorService;
use tests\TestCase;

final class IncrementalGeneratorTest extends TestCase
{
    use GeneratorFixture;

    private string $root;
    private TestableGeneratorService $service;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createGoldenTable();
    }

    public static function tearDownAfterClass(): void
    {
        self::dropGoldenTable();
        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir() . '/yd-incremental-' . bin2hex(random_bytes(5));
        $this->service = Container::make(TestableGeneratorService::class);
        $this->service->useTempRoots($this->root . '/server', $this->root);
    }

    protected function tearDown(): void
    {
        if (Db::schema()->hasColumn('gen_articles', 'phase_extra')) {
            Db::statement('ALTER TABLE gen_articles DROP COLUMN phase_extra');
        }
        if (is_dir($this->root)) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $file) {
                $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($this->root);
        }
        parent::tearDown();
    }

    private function request(string $title = '', array $overrides = []): GeneratorRequest
    {
        return new GeneratorRequest(self::GOLDEN_TABLE, self::GOLDEN_MODULE, self::GOLDEN_MODEL, $title, $overrides);
    }

    public function test_first_plan_is_read_only_and_generated_metadata_matches_its_module(): void
    {
        $plan = $this->service->plan($this->request());
        $this->assertFalse($plan['has_conflicts']);
        $this->assertDirectoryDoesNotExist($this->root);
        $this->service->generate($this->request());
        $catalog = (new ModuleCatalog())->inspect($this->root . '/server', $this->root, true);
        $this->assertSame([], $catalog['errors']);
        $this->assertCount(1, $catalog['modules']);
        $module = $catalog['modules'][0];
        $this->assertSame('demo.gen_article', $module['id']);
        $this->assertContains('demo.gen_article.status', $module['permissions']);
        $this->assertTrue($module['capabilities']['data_scope']);
        $this->assertContains('server/modules/demo/gen_article.json', $module['artifacts']);
    }

    public function test_schema_addition_keeps_custom_php_and_vue_code_and_updates_the_baseline(): void
    {
        $this->service->generate($this->request());
        $paths = $this->service->targetPaths($this->request());
        file_put_contents($paths['model'], "\n// custom model extension\n", FILE_APPEND);
        file_put_contents($paths['page'], "\n<!-- custom page extension -->\n", FILE_APPEND);
        Db::statement("ALTER TABLE gen_articles ADD COLUMN phase_extra int NULL COMMENT '新增字段'");
        $plan = $this->service->plan($this->request());
        $this->assertFalse($plan['has_conflicts'], json_encode($plan['files'], JSON_UNESCAPED_UNICODE));
        $result = $this->service->updateGenerated($this->request(), $plan['plan_token']);
        $this->assertTrue($result['applied']);
        $this->assertStringContainsString('custom model extension', file_get_contents($paths['model']));
        $this->assertStringContainsString('phase_extra', file_get_contents($paths['model']));
        $this->assertStringContainsString('custom page extension', file_get_contents($paths['page']));
        $baseline = json_decode(file_get_contents($this->root . '/server/generator-state/demo/gen_article/baseline.json'), true);
        $this->assertStringNotContainsString('custom model extension', $baseline['artifacts']['model']['content']);
        $this->assertSame([], (new ModuleCatalog())->inspect($this->root . '/server', $this->root, true)['errors']);
        $this->assertFileExists($this->root . '/' . $result['backup_directory'] . '/model.txt');
        $again = $this->service->plan($this->request());
        $this->assertFalse($again['has_conflicts']);
        $this->assertSame(['preserved', 'unchanged'], array_values(array_unique(array_column($again['files'], 'status'))));
    }

    public function test_overlap_blocks_the_entire_update_and_writes_conflict_material_separately(): void
    {
        $this->service->generate($this->request('original title'));
        $paths = $this->service->targetPaths($this->request());
        $originals = array_map(file_get_contents(...), $paths);
        file_put_contents($paths['service'], str_replace('original title', 'my custom title', $originals['service']));
        $before = array_map(file_get_contents(...), $paths);
        $request = $this->request('incoming title');
        $plan = $this->service->plan($request);
        $this->assertTrue($plan['has_conflicts']);
        $result = $this->service->updateGenerated($request, $plan['plan_token']);
        $this->assertFalse($result['applied']);
        $this->assertSame($before, array_map(file_get_contents(...), $paths));
        $this->assertFileExists($this->root . '/' . $result['conflict_directory'] . '/service.current.txt');
    }

    public function test_stale_plan_and_unknown_custom_files_cannot_be_overwritten(): void
    {
        $this->service->generate($this->request());
        $paths = $this->service->targetPaths($this->request());
        $plan = $this->service->plan($this->request('new title'));
        file_put_contents($paths['model'], "\n// newer edit\n", FILE_APPEND);
        try {
            $this->service->updateGenerated($this->request('new title'), $plan['plan_token']);
            $this->fail('Stale preview must be rejected');
        } catch (BusinessException $e) {
            $this->assertSame(409, $e->getCode());
        }
        unlink($this->root . '/server/generator-state/demo/gen_article/baseline.json');
        $unknown = $this->service->plan($this->request());
        $this->assertTrue($unknown['has_conflicts']);
        $this->assertStringContainsString('newer edit', file_get_contents($paths['model']));
    }

    public function test_saved_column_options_survive_cli_style_incremental_requests(): void
    {
        $overrides = ['title' => ['form_type' => 'textarea', 'searchable' => false, 'in_list' => false, 'in_form' => true]];
        $this->service->generate($this->request('saved title', $overrides));
        $plan = $this->service->plan($this->request());
        $this->assertFalse($plan['has_conflicts']);
        $this->assertSame(['unchanged'], array_values(array_unique(array_column($plan['files'], 'status'))));
    }

    public function test_symbolic_link_targets_are_rejected(): void
    {
        $this->service->generate($this->request());
        $paths = $this->service->targetPaths($this->request());
        $outside = $this->root . '/outside.txt';
        file_put_contents($outside, 'outside stays unchanged');
        unlink($paths['model']);
        symlink($outside, $paths['model']);
        try {
            $this->service->plan($this->request());
            $this->fail('Symlinks must be rejected');
        } catch (\RuntimeException) {
            $this->assertSame('outside stays unchanged', file_get_contents($outside));
        }
    }

    public function test_legacy_baseline_import_uses_original_snapshot_and_preserves_current_custom_code(): void
    {
        $this->service->generate($this->request());
        $paths = $this->service->targetPaths($this->request());
        $snapshot = $this->root . '/original-snapshot';
        foreach ($this->service->preview($this->request()) as $key => $file) {
            $path = $snapshot . '/' . $file['path'];
            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0o755, true);
            }
            copy($paths[$key], $path);
        }
        unlink($this->root . '/server/generator-state/demo/gen_article/baseline.json');
        file_put_contents($paths['model'], "\n// legacy custom extension\n", FILE_APPEND);
        $before = array_map(file_get_contents(...), $paths);
        $import = $this->service->importBaseline($this->request(), $snapshot);
        $this->assertCount($import['imported'], $paths);
        $this->assertSame($before, array_map(file_get_contents(...), $paths));
        $plan = $this->service->plan($this->request('updated legacy module'));
        $this->assertFalse($plan['has_conflicts']);
        $this->assertTrue($this->service->updateGenerated($this->request('updated legacy module'), $plan['plan_token'])['applied']);
        $this->assertStringContainsString('legacy custom extension', file_get_contents($paths['model']));
    }

    public function test_failed_render_never_deletes_an_existing_force_target(): void
    {
        $path = $this->root . '/server/app/model/demo/Class.php';
        mkdir(dirname($path), 0o755, true);
        $source = '<?php // existing custom source';
        file_put_contents($path, $source);
        try {
            $this->service->forceGenerate(new GeneratorRequest(self::GOLDEN_TABLE, self::GOLDEN_MODULE, 'Class', '', []));
            $this->fail('Invalid generated PHP must fail before writing');
        } catch (BusinessException) {
            $this->assertSame($source, file_get_contents($path));
        }
    }
}
