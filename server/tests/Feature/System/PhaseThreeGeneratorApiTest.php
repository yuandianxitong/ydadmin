<?php

declare(strict_types=1);

namespace tests\Feature\System;

use app\service\system\GeneratorService;
use support\Container;
use tests\Support\ApiTestCase;
use tests\Support\ConfigOverride;
use tests\Support\GeneratorFixture;
use tests\Support\TestableGeneratorService;

final class PhaseThreeGeneratorApiTest extends ApiTestCase
{
    use ConfigOverride;
    use GeneratorFixture;

    private string $root;
    private GeneratorService $previous;

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
        $this->root = sys_get_temp_dir() . '/yd-generator-api-' . bin2hex(random_bytes(5));
        // controller_reuse=false：HTTP 每次 make 控制器，必须替换 Service 绑定而非修改某个控制器实例。
        $this->previous = Container::get(GeneratorService::class);
        $service = Container::make(TestableGeneratorService::class);
        $service->useTempRoots($this->root . '/server', $this->root);
        Container::set(GeneratorService::class, $service);
    }

    protected function tearDown(): void
    {
        Container::set(GeneratorService::class, $this->previous);
        if (is_dir($this->root)) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($this->root);
        }
        $this->restoreConfig();
        parent::tearDown();
    }

    private function body(): array
    {
        return ['table_name' => self::GOLDEN_TABLE, 'module_name' => self::GOLDEN_MODULE, 'model_name' => self::GOLDEN_MODEL];
    }

    public function test_plan_and_apply_use_the_validated_contract_and_reject_stale_previews(): void
    {
        $admin = $this->actingAsAdmin(['system.generator.generate']);
        $body = $this->body();
        $this->post('/adminapi/system/generator/update', $body, $admin->token)->assertCode(422);
        $plan = $this->post('/adminapi/system/generator/plan', $body, $admin->token)->assertOk()->data();
        $this->assertFalse($plan['has_conflicts']);
        $this->assertDirectoryDoesNotExist($this->root);
        $applied = $this->post('/adminapi/system/generator/update', $body + ['plan_token' => $plan['plan_token']], $admin->token)->assertOk()->data();
        $this->assertTrue($applied['applied']);
        $this->assertFileExists($this->root . '/server/app/model/demo/GenArticle.php');
        $this->post('/adminapi/system/generator/update', $body + ['plan_token' => $plan['plan_token']], $admin->token)->assertCode(409);
    }

    public function test_generator_permission_and_production_guards_cover_incremental_endpoints(): void
    {
        $admin = $this->actingAsAdmin(['system.generator.list']);
        $this->post('/adminapi/system/generator/plan', $this->body(), $admin->token)->assertCode(403);
        $writer = $this->actingAsAdmin(['system.generator.generate']);
        $this->overrideConfig('app.debug', false);
        $this->post('/adminapi/system/generator/plan', $this->body(), $writer->token)->assertCode(400);
        $this->post('/adminapi/system/generator/update', $this->body() + ['plan_token' => str_repeat('a', 64)], $writer->token)->assertCode(400);
        $this->assertDirectoryDoesNotExist($this->root);
    }
}
