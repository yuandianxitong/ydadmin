<?php

declare(strict_types=1);

namespace tests\Feature\Generator;

use core\exception\BusinessException;
use core\generator\GeneratorRequest;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_first_plan_is_read_only_and_generation_only_creates_code_artifacts(): void
    {
        $plan = $this->service->plan($this->request());
        $this->assertFalse($plan['has_conflicts']);
        $this->assertDirectoryDoesNotExist($this->root);
        $this->service->generate($this->request());
        $this->assertFileExists($this->root . '/server/app/model/demo/GenArticle.php');
        $this->assertFileExists($this->root . '/server/app/adminapi/controller/demo/GenArticleController.php');
        $this->assertDirectoryDoesNotExist($this->root . '/server/modules');
        $this->assertCount(11, $plan['files']);
    }

    public function test_missing_docker_frontend_mount_stops_all_writes(): void
    {
        $old = $_ENV['GENERATOR_REQUIRE_ADMIN_MOUNT'] ?? null;
        $_ENV['GENERATOR_REQUIRE_ADMIN_MOUNT'] = 'true';
        try {
            $this->service->generate($this->request());
            $this->fail('未挂载前端源码时必须拒绝生成');
        } catch (\core\exception\BusinessException $exception) {
            $this->assertSame(lang('generator.frontend_mount_missing'), $exception->getMessage());
            $this->assertDirectoryDoesNotExist($this->root);
        } finally {
            if ($old === null) {
                unset($_ENV['GENERATOR_REQUIRE_ADMIN_MOUNT']);
            } else {
                $_ENV['GENERATOR_REQUIRE_ADMIN_MOUNT'] = $old;
            }
        }
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

    public function test_retired_artifacts_in_old_baselines_do_not_block_updates_or_lose_saved_options(): void
    {
        $overrides = ['title' => ['form_type' => 'textarea', 'searchable' => false, 'in_list' => false, 'in_form' => true]];
        $this->service->generate($this->request('saved title', $overrides));
        $paths = $this->service->targetPaths($this->request());
        file_put_contents($paths['model'], "\n// custom extension survives retirement\n", FILE_APPEND);
        $baselinePath = $this->root . '/server/generator-state/demo/gen_article/baseline.json';
        $baseline = json_decode(file_get_contents($baselinePath), true);
        $content = '{"schema_version":1}';
        $baseline['artifacts']['descriptor'] = [
            'path' => 'server/modules/demo/gen_article.json',
            'content' => $content,
            'sha256' => hash('sha256', $content),
        ];
        file_put_contents($baselinePath, json_encode($baseline, JSON_THROW_ON_ERROR));
        $before = file_get_contents($baselinePath);

        $plan = $this->service->plan($this->request());
        $this->assertFalse($plan['has_conflicts']);
        $this->assertSame($before, file_get_contents($baselinePath), 'Preview must not rewrite an old baseline');
        $this->assertNotContains('descriptor', array_column($plan['files'], 'key'));
        $result = $this->service->updateGenerated($this->request(), $plan['plan_token']);
        $this->assertTrue($result['applied']);
        $updated = json_decode(file_get_contents($baselinePath), true);
        $this->assertArrayNotHasKey('descriptor', $updated['artifacts']);
        $this->assertSame($baseline['options'], $updated['options']);
        $this->assertStringContainsString('custom extension survives retirement', file_get_contents($paths['model']));
        $this->assertDirectoryDoesNotExist($this->root . '/server/modules');
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

    public static function snapshotBaselines(): array
    {
        return ['source only' => [false], 'source and saved options' => [true]];
    }

    #[DataProvider('snapshotBaselines')]
    public function test_legacy_baseline_import_uses_original_snapshot_and_preserves_current_custom_code(bool $withBaseline): void
    {
        $overrides = ['title' => ['form_type' => 'textarea', 'searchable' => false, 'in_list' => false, 'in_form' => true]];
        $request = $this->request('snapshot title', $overrides);
        $this->service->generate($request);
        $paths = $this->service->targetPaths($this->request());
        $snapshot = $this->root . '/original-snapshot';
        foreach ($this->service->preview($request) as $key => $file) {
            $path = $snapshot . '/' . $file['path'];
            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0o755, true);
            }
            copy($paths[$key], $path);
        }
        $baselinePath = $this->root . '/server/generator-state/demo/gen_article/baseline.json';
        if ($withBaseline) {
            $originalBaseline = $snapshot . '/server/generator-state/demo/gen_article/baseline.json';
            mkdir(dirname($originalBaseline), 0o755, true);
            copy($baselinePath, $originalBaseline);
        }
        unlink($baselinePath);
        file_put_contents($paths['model'], "\n// legacy custom extension\n", FILE_APPEND);
        $before = array_map(file_get_contents(...), $paths);
        $import = $this->service->importBaseline($withBaseline ? $this->request() : $request, $snapshot);
        $this->assertCount($import['imported'], $paths);
        $this->assertSame($before, array_map(file_get_contents(...), $paths));
        $baseline = json_decode(file_get_contents($this->root . '/server/generator-state/demo/gen_article/baseline.json'), true);
        $this->assertSame(['table_comment' => 'snapshot title', 'overrides' => $overrides], $baseline['options']);
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
