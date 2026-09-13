<?php

declare(strict_types=1);

namespace tests\Feature\Command;

use app\command\MakeCrudCommand;
use app\service\system\GeneratorService;
use core\generator\GeneratorRequest;
use support\Container;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use tests\Support\GeneratorFixture;
use tests\Support\TestableGeneratorService;
use tests\TestCase;

/**
 * make:crud 是唯一直接往磁盘写代码的命令行入口，不经过 GeneratorController 的 validate()。
 * 与 GeneratorArtifactTest 同一手法（Task 8 已验证过）：把 GeneratorService 换成
 * TestableGeneratorService 并指向进程私有的临时目录，命令本身的代码路径（Container::get(
 * GeneratorService::class)）完全不变——测试只是在容器里换了一个绑定，不改命令实现。
 * 这样即使测试中途失败，也不会有产物残留进真实的 server/app、admin/src——那两处正是
 * composer lint/analyse/check:context 三道门禁的扫描路径。
 *
 * 模块名固定用 GeneratorFixture::GOLDEN_MODULE（demo），不用 business：GeneratorService 内部的
 * assertNames() 会把 business 当保留字拒掉（撞既有语言包 resource/lang/{locale}/business.php）。
 */
final class MakeCrudCommandTest extends TestCase
{
    use GeneratorFixture;

    private static string $tempRoot;

    private static TestableGeneratorService $testableService;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createGoldenTable();

        self::$tempRoot = sys_get_temp_dir() . '/make-crud-cmd-test-' . bin2hex(random_bytes(6));
        self::$testableService = Container::get(TestableGeneratorService::class);
        self::$testableService->useTempRoots(self::$tempRoot . '/server', self::$tempRoot);
        // 把容器里 GeneratorService::class 的解析结果换成落到临时目录的实现：命令内部原样
        // 调用 Container::get(GeneratorService::class)，因此拿到的就是这个换过根目录的实例。
        Container::instance()->set(GeneratorService::class, self::$testableService);
    }

    public static function tearDownAfterClass(): void
    {
        self::dropGoldenTable();
        // 换回一个全新的、指向真实 serverRoot()/repoRoot() 的 GeneratorService，不留下本测试类
        // 对容器单例的影响；make() 绕过 resolvedEntries 缓存，强制重新自动装配一份。
        Container::instance()->set(GeneratorService::class, Container::instance()->make(GeneratorService::class));
        self::removeTree(self::$tempRoot);
        parent::tearDownAfterClass();
    }

    private function runCommand(array $args): CommandTester
    {
        $tester = new CommandTester(new MakeCrudCommand());
        $tester->execute($args);

        return $tester;
    }

    /**
     * 用真实 preview() 拿本次会用到的全部产物的展示路径与内容：路径拼接规则只信一处
     * （GeneratorService::displayPath()/absolutePath()），测试不手拼第二份。
     *
     * @return array<string, array{path: string, content: string}>
     */
    private function previewArtifacts(string $model): array
    {
        $request = new GeneratorRequest(self::GOLDEN_TABLE, self::GOLDEN_MODULE, $model, '测试', []);

        return self::$testableService->preview($request);
    }

    /** 展示路径（相对仓库根）→ 临时目录下的绝对路径。 */
    private function absolutePathOf(string $displayPath): string
    {
        return self::$tempRoot . '/' . $displayPath;
    }

    private static function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path) && !is_link($path)) {
                self::removeTree($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    public function test_preview_lists_artifacts_without_writing(): void
    {
        $model = 'CrudCmdPreview';
        $artifacts = $this->previewArtifacts($model);

        $tester = $this->runCommand(['table' => self::GOLDEN_TABLE, '--module' => self::GOLDEN_MODULE, '--model' => $model, '--preview' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertStringContainsString('model', $tester->getDisplay());
        foreach ($artifacts as $artifact) {
            $this->assertFileDoesNotExist($this->absolutePathOf($artifact['path']), "--preview 不应落盘：{$artifact['path']}");
        }
    }

    public function test_generates_files_and_prints_reload_hint(): void
    {
        $model = 'CrudCmdCreate';
        $artifacts = $this->previewArtifacts($model);

        $tester = $this->runCommand(['table' => self::GOLDEN_TABLE, '--module' => self::GOLDEN_MODULE, '--model' => $model, '--comment' => '契约命令测试']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertStringContainsString('created', $tester->getDisplay());
        $this->assertStringContainsString('reload', $tester->getDisplay());
        foreach ($artifacts as $key => $artifact) {
            $this->assertFileExists($this->absolutePathOf($artifact['path']), "{$key} 没有落盘");
        }
    }

    /**
     * --force 会 unlink 已有产物再重新生成，唯独两个前端页面文件不动：
     * page（列表页 index.vue）与 form（表单组件 {Model}Form.vue）。
     *
     * form 以前不在豁免清单里：开发者手改了表单组件（加联动字段、改校验触发时机），之后为了同步
     * 后端改动跑一次 --force，手改内容就被永久删掉，没有备份也没有二次确认。两个文件在产物清单里
     * 是并列的前端页面（spec §6），README 写的也是「页面文件永远不会被覆盖」，读者会合理地以为
     * 两个 .vue 都受保护——所以两个都断言，且断言的是内容逐字不变，不只是文件还在。
     */
    public function test_force_overwrites_existing_files_except_the_two_front_end_pages(): void
    {
        $model = 'CrudCmdForce';
        $artifacts = $this->previewArtifacts($model);
        $this->runCommand(['table' => self::GOLDEN_TABLE, '--module' => self::GOLDEN_MODULE, '--model' => $model]);

        $marker = '手工改过，不应被覆盖';

        $protected = [];
        foreach (['page', 'form'] as $key) {
            $path = $this->absolutePathOf($artifacts[$key]['path']);
            file_put_contents($path, "<!-- {$marker} -->\n" . (string) file_get_contents($path));
            $protected[$key] = ['path' => $path, 'content' => (string) file_get_contents($path)];
        }

        // 对照组：后端产物也手改一份。它必须被 --force 抹掉——否则上面两条断言可能只是因为
        // 整个 --force 压根没生效，而不是因为前端页面真的受保护。注释追加在文件末尾，保持合法 PHP。
        $modelPath = $this->absolutePathOf($artifacts['model']['path']);
        file_put_contents($modelPath, (string) file_get_contents($modelPath) . "\n// {$marker}\n");

        $withoutForce = $this->runCommand(['table' => self::GOLDEN_TABLE, '--module' => self::GOLDEN_MODULE, '--model' => $model]);
        $this->assertStringContainsString('skipped', $withoutForce->getDisplay());
        foreach ($protected as $key => $file) {
            $this->assertSame($file['content'], file_get_contents($file['path']), "不加 --force 时手改的 {$key} 不受影响");
        }
        $this->assertStringContainsString($marker, (string) file_get_contents($modelPath), '不加 --force 时后端产物也不受影响');

        $withForce = $this->runCommand(['table' => self::GOLDEN_TABLE, '--module' => self::GOLDEN_MODULE, '--model' => $model, '--force' => true]);
        $this->assertSame(Command::SUCCESS, $withForce->getStatusCode(), $withForce->getDisplay());
        $this->assertStringContainsString('created', $withForce->getDisplay());
        foreach ($protected as $key => $file) {
            $this->assertSame($file['content'], file_get_contents($file['path']), "--force 也不能覆盖前端页面文件：{$key}");
        }
        $this->assertStringNotContainsString($marker, (string) file_get_contents($modelPath), '--force 应当重新生成后端产物');
    }

    public function test_rejects_unknown_table(): void
    {
        // 显式传 --module：不传就会先撞上下面 test_requires_module_option 测的那道检查，
        // 这条用例要测的是「表不存在」，不是「模块名缺失」，两条判断顺序不能互相遮挡。
        $tester = $this->runCommand(['table' => 'no_such_table_xyz', '--module' => self::GOLDEN_MODULE]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('不存在', $tester->getDisplay());
    }

    public function test_rejects_invalid_module_name(): void
    {
        $tester = $this->runCommand(['table' => self::GOLDEN_TABLE, '--module' => 'Bad Module', '--model' => 'CrudCmdBadModule']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('--module', $tester->getDisplay());
    }

    /** --module 没有默认值：不传时必须给出可读提示，而不是猜一个模块名接着往下跑。 */
    public function test_requires_module_option(): void
    {
        $tester = $this->runCommand(['table' => self::GOLDEN_TABLE]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('--module', $tester->getDisplay());
    }

    /**
     * business 是保留字（撞 M1a 已提交的 resource/lang/{zh_CN,en}/business.php），命令层的
     * 端到端确认——Service 层的 assertNames() 已经单独测过一次这条规则本身成不成立（Task 8 范围），
     * 这里只确认 make:crud 这条不经过 GeneratorController 校验的路径，异常照样能被命令捕获、
     * 干净地以非零码退出，不会抛出一个没接住的异常把命令行搞崩。具体提示文案由 Task 8 的
     * assertNames() 决定，这里不假设精确文案，只断言命令确实失败且有可读输出。
     */
    public function test_rejects_reserved_module_name(): void
    {
        $tester = $this->runCommand(['table' => self::GOLDEN_TABLE, '--module' => 'business', '--model' => 'CrudCmdReserved']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertNotSame('', trim($tester->getDisplay()), '保留字被拒时应给出可读提示，不能是空输出');
    }
}
