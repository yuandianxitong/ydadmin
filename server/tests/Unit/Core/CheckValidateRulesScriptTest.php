<?php

declare(strict_types=1);

namespace tests\Unit\Core;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use tests\TestCase;

/**
 * check:context 规则七的检查器已从 awk 逐行正则改写成 scripts/check-validate-rules.php
 * （token_get_all() 逐 token 比对）。改写的起因是评审在隔离目录逐条实测出 awk 版两头都漏：
 *
 *   漏拦（本该报错却放行，旧 awk 版 exit=0）：
 *     1. 箭头两侧带空格：$this -> validate(...)
 *     2. 方法链跨行：$this\n->validate(...)
 *     3. 行尾注释里恰好出现正确方法名：...); // TODO: $this->storeRules()
 *   误拦（本该放行却报错，旧 awk 版 exit=1）：
 *     4. 格式正确但跨多行的 validate() 调用
 *     5. 注释里提到 $this->validate( 字面文本
 *     6. `public function` 与方法名跨行
 *
 * 旧 awk 版在这六个样本上的实跑结果记在 task-11-report.md 的补充记录里（本类已经不含 awk
 * 实现，没法在同一个测试里现跑对照——旧版逻辑已被完全替换，不是并存两套）。
 *
 * 这里直接驱动 scripts/check-validate-rules.php（不经过完整的 check-context-discipline.sh），
 * 在 sys_get_temp_dir() 下现造一个临时的 controller 目录，跑完就删——不依赖仓库里任何真实
 * 文件，也不污染真实仓库。7、8 两个用例补足「必须报错」分支的覆盖广度：第二参数指向别的
 * 动作的 Rules()、规则塞进变量。
 */
final class CheckValidateRulesScriptTest extends TestCase
{
    private static string $scriptPath;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$scriptPath = base_path('scripts/check-validate-rules.php');
    }

    /** 漏拦样本 1：箭头两侧带空格——旧 awk 版按字面串 "$this->validate(" 找不到这一行，直接漏检。 */
    public function test_case1_arrow_with_spaces_is_caught(): void
    {
        $this->assertJudgedAsViolation(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                namespace app\adminapi\controller\testdemo;

                use core\base\Controller;
                use support\Response;
                use Webman\Http\Request;

                class FooController extends Controller
                {
                    public function store(Request $request): Response
                    {
                        $data = $this -> validate($this->body($request), ['title' => 'required']);

                        return $this->success($data);
                    }

                    private function storeRules(): array
                    {
                        return ['title' => 'required'];
                    }
                }
                PHP,
            "['title'=>'required']"
        );
    }

    /** 漏拦样本 2：方法链跨行——"$this" 与 "->validate(" 分属两行，awk 逐行正则同样匹配不到。 */
    public function test_case2_chained_call_across_lines_is_caught(): void
    {
        $this->assertJudgedAsViolation(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                namespace app\adminapi\controller\testdemo;

                use core\base\Controller;
                use support\Response;
                use Webman\Http\Request;

                class FooController extends Controller
                {
                    public function store(Request $request): Response
                    {
                        $data = $this
                            ->validate($this->body($request), ['title' => 'required']);

                        return $this->success($data);
                    }

                    private function storeRules(): array
                    {
                        return ['title' => 'required'];
                    }
                }
                PHP,
            "['title'=>'required']"
        );
    }

    /** 漏拦样本 3：行尾注释里恰好出现正确方法名——旧 awk 版对整行做子串匹配，注释也算数。 */
    public function test_case3_trailing_comment_with_correct_name_is_caught(): void
    {
        $this->assertJudgedAsViolation(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                namespace app\adminapi\controller\testdemo;

                use core\base\Controller;
                use support\Response;
                use Webman\Http\Request;

                class FooController extends Controller
                {
                    public function store(Request $request): Response
                    {
                        $data = $this->validate($this->body($request), ['title' => 'required']); // TODO: $this->storeRules()

                        return $this->success($data);
                    }

                    private function storeRules(): array
                    {
                        return ['title' => 'required'];
                    }
                }
                PHP,
            "['title'=>'required']"
        );
    }

    /** 误拦样本 4：格式正确但跨多行的 validate() 调用——旧 awk 版只看首行，找不到第三行的 storeRules()。 */
    public function test_case4_multiline_correct_call_is_allowed(): void
    {
        $this->assertJudgedAsClean(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                namespace app\adminapi\controller\testdemo;

                use core\base\Controller;
                use support\Response;
                use Webman\Http\Request;

                class FooController extends Controller
                {
                    public function store(Request $request): Response
                    {
                        $data = $this->validate(
                            $this->body($request),
                            $this->storeRules(),
                            $this->messages()
                        );

                        return $this->success($data);
                    }

                    private function storeRules(): array
                    {
                        return ['title' => 'required'];
                    }

                    private function messages(): array
                    {
                        return [];
                    }
                }
                PHP
        );
    }

    /** 误拦样本 5：注释里提到 $this->validate( 字面文本——旧 awk 版把注释行当代码扫描。 */
    public function test_case5_comment_mentioning_validate_is_allowed(): void
    {
        $this->assertJudgedAsClean(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                namespace app\adminapi\controller\testdemo;

                use core\base\Controller;
                use support\Response;
                use Webman\Http\Request;

                class FooController extends Controller
                {
                    public function store(Request $request): Response
                    {
                        // legacy: used to call $this->validate($data, $this->oldRules())
                        $data = $this->validate($this->body($request), $this->storeRules(), $this->messages());

                        return $this->success($data);
                    }

                    private function storeRules(): array
                    {
                        return ['title' => 'required'];
                    }

                    private function messages(): array
                    {
                        return [];
                    }
                }
                PHP
        );
    }

    /** 误拦样本 6：public function 与方法名跨行——旧 awk 版找不到「function 后紧跟标识符」，当前动作名失效。 */
    public function test_case6_function_name_on_next_line_is_allowed(): void
    {
        $this->assertJudgedAsClean(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                namespace app\adminapi\controller\testdemo;

                use core\base\Controller;
                use support\Response;
                use Webman\Http\Request;

                class FooController extends Controller
                {
                    public function
                        store(Request $request): Response
                    {
                        $data = $this->validate($this->body($request), $this->storeRules(), $this->messages());

                        return $this->success($data);
                    }

                    private function storeRules(): array
                    {
                        return ['title' => 'required'];
                    }

                    private function messages(): array
                    {
                        return [];
                    }
                }
                PHP
        );
    }

    /** 补充样本 7：第二参数是别的动作的 Rules()——store() 里错用了 updateRules()，必须报错。 */
    public function test_case7_second_argument_points_to_a_different_actions_rules_is_caught(): void
    {
        $this->assertJudgedAsViolation(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                namespace app\adminapi\controller\testdemo;

                use core\base\Controller;
                use support\Response;
                use Webman\Http\Request;

                class FooController extends Controller
                {
                    public function store(Request $request): Response
                    {
                        $data = $this->validate($this->body($request), $this->updateRules(), $this->messages());

                        return $this->success($data);
                    }

                    private function updateRules(): array
                    {
                        return ['title' => 'sometimes|required'];
                    }

                    private function messages(): array
                    {
                        return [];
                    }
                }
                PHP,
            '$this->updateRules()'
        );
    }

    /** 补充样本 8：规则先赋值给变量再传入——第二参数不是字面的 $this->{action}Rules() 调用，必须报错。 */
    public function test_case8_rules_stored_in_a_variable_is_caught(): void
    {
        $this->assertJudgedAsViolation(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                namespace app\adminapi\controller\testdemo;

                use core\base\Controller;
                use support\Response;
                use Webman\Http\Request;

                class FooController extends Controller
                {
                    public function store(Request $request): Response
                    {
                        $rules = $this->storeRules();
                        $data = $this->validate($this->body($request), $rules, $this->messages());

                        return $this->success($data);
                    }

                    private function storeRules(): array
                    {
                        return ['title' => 'required'];
                    }

                    private function messages(): array
                    {
                        return [];
                    }
                }
                PHP,
            '$rules'
        );
    }

    private function assertJudgedAsViolation(string $source, string $expectedFragment): void
    {
        [$exitCode, $text] = $this->runScriptAgainst($source);

        $this->assertNotSame(0, $exitCode, "本该报错的写法没有被拦下。实际输出：\n{$text}");
        $this->assertStringContainsString(
            'app/adminapi/controller/testdemo/FooController.php',
            $text,
            "报错文本没有点名违规文件：\n{$text}"
        );
        $this->assertStringContainsString(
            $expectedFragment,
            $text,
            "报错文本里没有看到期望的“实际是 ...”片段，判定依据可能不对：\n{$text}"
        );
    }

    private function assertJudgedAsClean(string $source): void
    {
        [$exitCode, $text] = $this->runScriptAgainst($source);

        $this->assertSame(0, $exitCode, "本该放行的写法被误拦了。实际输出：\n{$text}");
    }

    /** @return array{0: int, 1: string} */
    private function runScriptAgainst(string $source): array
    {
        $root = sys_get_temp_dir() . '/check-validate-rules-' . bin2hex(random_bytes(6));
        $controllerDir = $root . '/app/adminapi/controller/testdemo';
        mkdir($controllerDir, 0755, true);
        file_put_contents($controllerDir . '/FooController.php', $source);

        try {
            exec(sprintf(
                'php %s %s %s 2>&1',
                escapeshellarg(self::$scriptPath),
                escapeshellarg($root),
                escapeshellarg($root . '/app/adminapi/controller')
            ), $output, $exitCode);

            return [$exitCode, implode("\n", $output)];
        } finally {
            $this->removeDirectory($root);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            /** @var SplFileInfo $item */
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
