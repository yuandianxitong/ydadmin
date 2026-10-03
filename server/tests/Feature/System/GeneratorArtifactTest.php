<?php

declare(strict_types=1);

namespace tests\Feature\System;

use app\service\system\GeneratorService;
use core\exception\BusinessException;
use core\exception\ValidationException;
use core\generator\ArtifactWriter;
use core\generator\GeneratorRequest;
use support\Container;
use tests\Support\ConfigOverride;
use tests\Support\GeneratorFixture;
use tests\TestCase;

/** 把产物打到 runtime/generator-test 下，不碰真实的 app/ 与 admin/（spec §11.2）。 */
final class TempRootGeneratorService extends GeneratorService
{
    public const ROOT = 'generator-test';

    protected function serverRoot(): string
    {
        return runtime_path(self::ROOT) . '/server';
    }

    protected function repoRoot(): string
    {
        return runtime_path(self::ROOT);
    }
}

final class GeneratorArtifactTest extends TestCase
{
    use ConfigOverride;
    use GeneratorFixture;

    /** 底稿 §4 的产物顺序，一个字都不能差：前端取 Object.keys(data)[0] 作默认页签。 */
    private const KEYS = [
        'model', 'repository', 'service', 'controller',
        'lang_zh', 'lang_en', 'api', 'page', 'form', 'menu',
    ];

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
        $this->overrideConfig('app.debug', true);
        $this->removeRoot();
    }

    protected function tearDown(): void
    {
        $this->removeRoot();
        $this->restoreConfig();
        parent::tearDown();
    }

    public function test_artifacts_come_back_in_the_documented_order(): void
    {
        $preview = $this->service()->preview($this->request());

        $this->assertSame(self::KEYS, array_keys($preview), '产物顺序决定前端默认页签，model 必须在最前');
        foreach ($preview as $key => $file) {
            $this->assertNotSame('', $file['content'], "{$key} 渲染出了空内容");
            $this->assertStringStartsNotWith('/', $file['path'], "{$key} 的 path 不能是服务器绝对路径");
        }
    }

    public function test_paths_are_relative_to_the_repo_root_and_use_two_bases(): void
    {
        $preview = $this->service()->preview($this->request());

        $this->assertSame('server/app/model/demo/GenArticle.php', $preview['model']['path']);
        $this->assertSame('server/database/generated/demo-menu.sql', $preview['menu']['path']);
        $this->assertSame('admin/src/api/gen-article.ts', $preview['api']['path']);
        $this->assertSame('admin/src/views/demo/gen-article/index.vue', $preview['page']['path']);
        $this->assertSame('admin/src/views/demo/gen-article/components/GenArticleForm.vue', $preview['form']['path']);
    }

    /** spec §11.3：preview 的每个 content 与 generate 落盘文件的字节逐一相等。 */
    public function test_preview_is_byte_for_byte_what_gets_written(): void
    {
        $service = $this->service();
        $preview = $service->preview($this->request());
        $result = $service->generate($this->request());

        $this->assertCount(count(self::KEYS), $result['files']);
        foreach ($result['files'] as $file) {
            $this->assertSame('created', $file['status'], "{$file['path']} 应当被创建：" . ($file['reason'] ?? ''));
        }

        $root = runtime_path(TempRootGeneratorService::ROOT);
        foreach ($preview as $key => $file) {
            $onDisk = $root . '/' . $file['path'];
            $this->assertFileExists($onDisk, "{$key} 没有落盘");
            $this->assertSame(
                $file['content'],
                (string) file_get_contents($onDisk),
                "{$key} 的预览内容与落盘内容不一致——preview 与 generate 必须共用同一条渲染路径"
            );
        }
    }

    public function test_existing_files_are_skipped_and_never_modified(): void
    {
        $service = $this->service();
        $target = runtime_path(TempRootGeneratorService::ROOT) . '/server/app/model/demo/GenArticle.php';
        @mkdir(dirname($target), 0o755, true);
        file_put_contents($target, '<?php // 手改过的文件，生成器不许碰');

        $result = $service->generate($this->request());
        $model = $this->fileEntry($result['files'], 'server/app/model/demo/GenArticle.php');

        $this->assertSame('skipped', $model['status']);
        $this->assertSame(lang('generator.file_exists'), $model['reason'] ?? '');
        $this->assertSame('<?php // 手改过的文件，生成器不许碰', (string) file_get_contents($target), '生成器只创建新文件，从不修改已有文件');
    }

    public function test_generated_controller_declares_attribute_routes(): void
    {
        $content = $this->service()->preview($this->request())['controller']['content'];
        $this->assertStringContainsString('extends AuthenticatedController', $content);
        $this->assertStringContainsString("#[RouteGroup('/adminapi/demo/gen-article')]", $content);
        $this->assertStringContainsString("#[Get('')]", $content);
        $this->assertStringContainsString("#[Post('/batch-delete')]", $content);
        $this->assertStringContainsString("#[Put('/{id:\\d+}/status')]", $content);
        $this->assertStringContainsString("#[Get('/{id:\\d+}')]", $content);
        $this->assertStringContainsString("#[Post('')]", $content);
        $this->assertStringContainsString("#[Put('/{id:\\d+}')]", $content);
        $this->assertStringContainsString("#[Delete('/{id:\\d+}')]", $content);
        $this->assertStringContainsString('reload', $content);
        $this->assertStringNotContainsString('config/route/', $content);
        $this->assertArrayNotHasKey('route', $this->service()->generate($this->request()));
    }

    /** 控制器最后写：它被扫描之后路由才存在。 */
    public function test_the_controller_artifact_is_always_written_last(): void
    {
        $files = [];
        foreach (self::KEYS as $key) {
            $files[$key] = ['path' => "/tmp/{$key}", 'content' => ''];
        }
        $order = (new \ReflectionMethod(ArtifactWriter::class, 'writeOrder'))->invoke(new ArtifactWriter(), $files);

        $this->assertSame('controller', end($order));
        $this->assertSame(count(self::KEYS), count($order), '落盘顺序不得丢产物');
    }

    /** spec §4.5：APP_DEBUG=false 时不渲染、不落盘。 */
    public function test_writes_are_disabled_when_debug_is_off(): void
    {
        $this->overrideConfig('app.debug', false);
        $service = $this->service();

        foreach (['preview', 'generate'] as $method) {
            try {
                $service->{$method}($this->request());
                $this->fail("{$method} 在 APP_DEBUG=false 时必须抛业务异常");
            } catch (BusinessException $e) {
                $this->assertSame(lang('generator.disabled_in_production'), $e->getMessage());
            }
        }
        $this->assertDirectoryDoesNotExist(runtime_path(TempRootGeneratorService::ROOT), '被禁用时一个字节都不许落盘');
    }

    /**
     * 保留模块名在 Service 层也要挡住：make:crud 不经过控制器，控制器那道 not_in 规则管不到它。
     * 撞上既有语言分组 → 语言包产物被判「已存在」跳过 → 生成代码里的 lang() 静默回显 key。
     */
    public function test_reserved_module_names_are_rejected_by_the_service(): void
    {
        foreach (['admin_log', 'auth', 'business', 'messages', 'validation', 'generator'] as $reserved) {
            $request = new GeneratorRequest(self::GOLDEN_TABLE, $reserved, self::GOLDEN_MODEL, self::GOLDEN_COMMENT, []);
            try {
                $this->service()->preview($request);
                $this->fail("保留模块名 {$reserved} 必须被拒");
            } catch (ValidationException $e) {
                $this->assertSame(lang('generator.module_name_reserved'), $e->errors()['module_name'] ?? '');
            }
        }
        $this->assertDirectoryDoesNotExist(runtime_path(TempRootGeneratorService::ROOT));
    }

    private function service(): TempRootGeneratorService
    {
        return Container::get(TempRootGeneratorService::class);
    }

    private function request(): GeneratorRequest
    {
        // 模块名用 demo（GeneratorFixture::GOLDEN_MODULE）而不是 business：business 是保留字
        // （撞既有语言分组），会被 assertNames() 直接拒掉。
        return new GeneratorRequest(self::GOLDEN_TABLE, self::GOLDEN_MODULE, self::GOLDEN_MODEL, self::GOLDEN_COMMENT, []);
    }

    /**
     * @param list<array{path: string, status: string, reason?: string}> $files
     * @return array{path: string, status: string, reason?: string}
     */
    private function fileEntry(array $files, string $path): array
    {
        foreach ($files as $file) {
            if ($file['path'] === $path) {
                return $file;
            }
        }
        $this->fail("响应里没有 {$path}");
    }

    /** 夹具自行清理落盘产物（底稿 §1）。 */
    private function removeRoot(): void
    {
        $root = runtime_path(TempRootGeneratorService::ROOT);
        if (!is_dir($root)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($root);
    }
}
