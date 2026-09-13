<?php

declare(strict_types=1);

namespace tests\Feature\Generator;

use core\generator\GeneratorRequest;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use support\Container;
use support\Db;
use tests\Support\TestableGeneratorService;
use tests\TestCase;

/**
 * 门禁测试（spec §11.2）：对生成产物跑 php -l / phpstan level 6 / cs-fixer / check:context 四道
 * 静态检查。这只证明产物「能过检查」，不证明「能跑」——能跑的部分见 GeneratedCodeRunnableTest。
 *
 * 产物落在 server/runtime/ 下的临时目录（`.gitignore` 已排除，phpstan/cs-fixer/check:context
 * 默认也都不扫这里），用 TestableGeneratorService 生成——测的是生成器真实的落盘逻辑，不是重新
 * 手写一份模板渲染流程的复刻。黄金夹具表 gen_articles 与 spec §5 底稿逐字节一致，由本类自己建、
 * 自己删；模块名用 demo（不用 business，避免撞上 M1a 已有的 resource/lang/{zh_CN,en}/business.php，
 * 语言包产物会因为「已存在」被判 skipped 而不是 created，测不出生成器本身的问题）。
 */
final class GeneratedCodeGateTest extends TestCase
{
    private static string $tmpRoot;

    /**
     * 需要过 php -l / phpstan / cs-fixer 的产物：generate() 返回的 path 是「展示路径」
     * （server/app/... 这种，相对仓库根，见 GeneratorService::displayPath()），不是磁盘绝对
     * 路径——这里存的是拼过 self::$tmpRoot 之后的真实绝对路径，否则 php -l / phpstan 会在
     * 当前工作目录（server/）下去找一个并不存在的 "server/server/app/..." 而全部报「文件不存在」。
     *
     * @var list<string>
     */
    private static array $phpFiles = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createFixtureTable();
        self::$tmpRoot = base_path('runtime') . '/generator-gate-' . bin2hex(random_bytes(6));
        self::generateModule(self::$tmpRoot);
    }

    public static function tearDownAfterClass(): void
    {
        Db::statement('DROP TABLE IF EXISTS `gen_articles`');
        self::removeDirectory(self::$tmpRoot);
        parent::tearDownAfterClass();
    }

    private static function createFixtureTable(): void
    {
        Db::statement('DROP TABLE IF EXISTS `gen_articles`');
        Db::statement(<<<'SQL'
            CREATE TABLE `gen_articles` (
              `id` int unsigned NOT NULL AUTO_INCREMENT,
              `title` varchar(200) NOT NULL COMMENT '标题',
              `summary` varchar(500) DEFAULT NULL COMMENT '摘要',
              `content` longtext COMMENT '正文',
              `cover_image` varchar(255) DEFAULT NULL COMMENT '封面图',
              `category` enum('news','tech','life') NOT NULL DEFAULT 'news' COMMENT '分类',
              `price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '价格',
              `view_count` int unsigned NOT NULL DEFAULT '0' COMMENT '浏览量',
              `slug` varchar(100) NOT NULL COMMENT '别名',
              `published_at` datetime DEFAULT NULL COMMENT '发布时间',
              `status` tinyint NOT NULL DEFAULT '1' COMMENT '状态',
              `sort` int NOT NULL DEFAULT '0' COMMENT '排序',
              `created_by` int unsigned DEFAULT NULL,
              `dept_id` int unsigned DEFAULT NULL,
              `created_at` datetime DEFAULT NULL,
              `updated_at` datetime DEFAULT NULL,
              `deleted_at` datetime DEFAULT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_slug` (`slug`),
              KEY `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='生成器夹具表'
            SQL);
    }

    private static function generateModule(string $root): void
    {
        $service = Container::get(TestableGeneratorService::class);
        $service->useTempRoots($root . '/server', $root . '/repo');
        $result = $service->generate(new GeneratorRequest('gen_articles', 'demo', 'GenArticle', '生成器夹具表', []));

        foreach ($result['files'] as $file) {
            if (str_ends_with($file['path'], '.php')) {
                // $file['path'] 都是 'server/...'（server 基准的展示路径始终带这个字面前缀，
                // 见 GeneratorService::displayPath()），拼 $root 就是磁盘上的真实绝对路径。
                self::$phpFiles[] = $root . '/' . $file['path'];
            }
        }
    }

    private static function removeDirectory(string $dir): void
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

    public function test_generated_php_files_have_no_syntax_errors(): void
    {
        $this->assertNotEmpty(self::$phpFiles, '生成没有产出任何 .php 文件，先检查 Task 8 的 generate() 是否正常');
        foreach (self::$phpFiles as $file) {
            $this->assertFileExists($file, '产物没有真的落到磁盘上，phpFiles 收集的路径可能拼错了');
            exec('php -l ' . escapeshellarg($file) . ' 2>&1', $output, $exitCode);
            $this->assertSame(0, $exitCode, "{$file}：\n" . implode("\n", $output));
            $this->assertStringContainsString('No syntax errors detected', implode("\n", $output));
        }
    }

    public function test_generated_php_files_pass_phpstan_level_6(): void
    {
        // 只扫 app/：真实 phpstan.neon 的 paths 只有 app、core、scripts（见 server/phpstan.neon），
        // 生成器只往 app/ 落 PHP 文件（model/repository/service/controller），config/ 与
        // resource/ 从不在真实门禁的扫描范围内——若把 config/route/{module}.php 也塞进 paths，
        // 会撞上一个跟生成器质量无关的假阳性：该文件设计上要被 config/route.php 的闭包
        // `require`（$adminAuth 由外层 use 进来，见 config/route.php 那段注释），脱离那个闭包
        // 单独跑 phpstan 必然报 "$adminAuth might not be defined"——这是路由文件的分发方式决定的、
        // 真实门禁也从未检查过的东西，不是生成产物的缺陷，不该出现在这条测试里。
        $neonPath = self::$tmpRoot . '/phpstan-generated.neon';
        $neon = "parameters:\n"
            . "    level: 6\n"
            . "    paths:\n"
            . '        - "' . addslashes(self::$tmpRoot . '/server/app') . "\"\n"
            . "    bootstrapFiles:\n"
            . '        - "' . addslashes(base_path() . '/vendor/autoload.php') . "\"\n"
            . "    treatPhpDocTypesAsCertain: false\n"
            . "    universalObjectCratesClasses:\n"
            . "        - Webman\\Http\\Request\n";
        file_put_contents($neonPath, $neon);

        exec(sprintf(
            'cd %s && vendor/bin/phpstan analyse -c %s --memory-limit=1G --no-progress 2>&1',
            escapeshellarg(base_path()),
            escapeshellarg($neonPath)
        ), $output, $exitCode);

        $this->assertSame(0, $exitCode, implode("\n", $output));
    }

    public function test_generated_php_files_pass_cs_fixer(): void
    {
        // 同上：只扫 app/，与真实 .php-cs-fixer.php 的 in([app, core, tests, scripts]) 对齐——
        // config/、resource/ 从不在真实门禁范围内（真实的 config/route.php 本身也没有
        // declare(strict_types=1)，同样因为在这个 Finder 范围之外，不是被特批豁免）。
        // --path-mode=intersection 会自动把 self::$phpFiles 里落在 Finder 范围外的
        // route.php/lang 文件排除出分析——这正是我们想要的：它们不该被这条门禁测试判定。
        $configPath = self::$tmpRoot . '/.php-cs-fixer.generated.php';
        $finderTarget = var_export(self::$tmpRoot . '/server/app', true);
        file_put_contents($configPath, <<<PHP
            <?php

            \$finder = PhpCsFixer\\Finder::create()->in({$finderTarget});

            return (new PhpCsFixer\\Config())
                ->setRules([
                    '@PSR12' => true,
                    'array_syntax' => ['syntax' => 'short'],
                    'declare_strict_types' => true,
                    'no_unused_imports' => true,
                    'ordered_imports' => ['sort_algorithm' => 'alpha'],
                ])
                ->setRiskyAllowed(true)
                ->setFinder(\$finder);
            PHP);

        $files = implode(' ', array_map('escapeshellarg', self::$phpFiles));
        exec(sprintf(
            'cd %s && vendor/bin/php-cs-fixer fix --config=%s --dry-run --diff --path-mode=intersection -- %s 2>&1',
            escapeshellarg(base_path()),
            escapeshellarg($configPath),
            $files
        ), $output, $exitCode);

        $this->assertSame(0, $exitCode, implode("\n", $output));
    }

    public function test_generated_code_passes_check_context_discipline(): void
    {
        exec(sprintf(
            'bash %s %s 2>&1',
            escapeshellarg(base_path() . '/scripts/check-context-discipline.sh'),
            escapeshellarg(self::$tmpRoot . '/server')
        ), $output, $exitCode);

        $this->assertSame(0, $exitCode, implode("\n", $output));
    }

    // -------------------------------------------------------------------
    // 反向用例（评审要求）：以上四条只证明「干净的产物能过」，不证明门禁真的在看内容——如果
    // check-context-discipline.sh 的路径解析或目录过滤将来被改坏成「悄悄扫了个空目录」，
    // 四条 ✅ 会照样打印，只有这里的「注入已知违规、断言必须变红」能发现。断言不能只看退出码
    // 非零：还要确认报出的确实是注入的那一处（文件名 + 规则文案），否则一个因为别的原因失败的
    // 门禁也会让用例通过。每条用例都在 try/finally 里原地改、原地还原，不依赖其它用例的执行
    // 顺序，也不污染后续用例或真实仓库。

    /**
     * check:check 规则二（本任务新增的可选路径参数最需要被钉住）：往生成的 Service 里注入一句
     * 真实的 Db:: 调用，断言 check-context-discipline.sh 传自定义路径时必须变红，且报错文本
     * 点名了被注入的那个文件——不是随便什么原因导致的非零退出码。
     */
    public function test_check_context_discipline_catches_an_injected_db_call_in_service(): void
    {
        $serviceFile = $this->findGeneratedFile('/app/service/demo/GenArticleService.php');
        $original = (string) file_get_contents($serviceFile);
        $mutated = $this->insertStatementBeforeFinalBrace(
            $original,
            "\n    public function ctxCheckInjectedViolation(): int\n    {\n        return (int) \\support\\Db::table('gen_articles')->count();\n    }\n"
        );
        file_put_contents($serviceFile, $mutated);

        try {
            exec(sprintf(
                'bash %s %s 2>&1',
                escapeshellarg(base_path() . '/scripts/check-context-discipline.sh'),
                escapeshellarg(self::$tmpRoot . '/server')
            ), $output, $exitCode);
            $text = implode("\n", $output);

            $this->assertNotSame(0, $exitCode, "注入了真实的 Db:: 调用之后，check:context 必须变红。实际输出：\n{$text}");
            $this->assertStringContainsString(
                '直接调用了 Db::',
                $text,
                "退出码非零，但报错文本里没有规则二的文案，可能是别的原因导致的失败：\n{$text}"
            );
            $this->assertStringContainsString(
                'app/service/demo/GenArticleService.php',
                $text,
                "报错文本没有点名被注入违规的那个文件：\n{$text}"
            );
        } finally {
            file_put_contents($serviceFile, $original);
            $this->assertSame($original, (string) file_get_contents($serviceFile), '注入的违规必须清理干净，不能残留污染后续用例');
        }
    }

    /**
     * check:context 规则四：往生成的 Repository 里注入 `$this->model->`（绕开 `$this->query()`
     * 注入的数据权限），同样断言必须变红且点名具体文件——覆盖与规则二不同的 grep 目标/消息文案，
     * 进一步确认自定义路径参数底下各条规则都真的在扫内容，不是只有第一条凑巧还生效。
     */
    public function test_check_context_discipline_catches_an_injected_model_property_access_in_repository(): void
    {
        $repositoryFile = $this->findGeneratedFile('/app/repository/demo/GenArticleRepository.php');
        $original = (string) file_get_contents($repositoryFile);
        $mutated = $this->insertStatementBeforeFinalBrace(
            $original,
            "\n    public function ctxCheckInjectedViolation(): int\n    {\n        return \$this->model->where('id', 1)->count();\n    }\n"
        );
        file_put_contents($repositoryFile, $mutated);

        try {
            exec(sprintf(
                'bash %s %s 2>&1',
                escapeshellarg(base_path() . '/scripts/check-context-discipline.sh'),
                escapeshellarg(self::$tmpRoot . '/server')
            ), $output, $exitCode);
            $text = implode("\n", $output);

            $this->assertNotSame(0, $exitCode, "注入了 \$this->model-> 之后，check:context 必须变红。实际输出：\n{$text}");
            $this->assertStringContainsString(
                '直接使用了 $this->model->',
                $text,
                "退出码非零，但报错文本里没有规则四的文案，可能是别的原因导致的失败：\n{$text}"
            );
            $this->assertStringContainsString(
                'app/repository/demo/GenArticleRepository.php',
                $text,
                "报错文本没有点名被注入违规的那个文件：\n{$text}"
            );
        } finally {
            file_put_contents($repositoryFile, $original);
            $this->assertSame($original, (string) file_get_contents($repositoryFile), '注入的违规必须清理干净，不能残留污染后续用例');
        }
    }

    /** php -l：往生成的 Model 追加一段语法错误，断言必须变红并点名文件。 */
    public function test_php_lint_catches_an_injected_syntax_error(): void
    {
        $modelFile = $this->findGeneratedFile('/app/model/demo/GenArticle.php');
        $original = (string) file_get_contents($modelFile);
        file_put_contents($modelFile, $original . "\nif (true) {\n    echo 'missing closing brace';\n");

        try {
            exec('php -l ' . escapeshellarg($modelFile) . ' 2>&1', $output, $exitCode);
            $text = implode("\n", $output);

            $this->assertNotSame(0, $exitCode, "注入语法错误之后 php -l 必须变红。实际输出：\n{$text}");
            $this->assertStringContainsString('GenArticle.php', $text, "报错文本没有点名被注入违规的那个文件：\n{$text}");
            $this->assertStringContainsStringIgnoringCase('error', $text, "报错文本看不出是语法错误：\n{$text}");
        } finally {
            file_put_contents($modelFile, $original);
            $this->assertSame($original, (string) file_get_contents($modelFile), '注入的违规必须清理干净，不能残留污染后续用例');
        }
    }

    /** cs-fixer：往生成的 Repository 追加一段长数组语法（违反 array_syntax=short），断言必须变红并点名文件。 */
    public function test_cs_fixer_catches_an_injected_style_violation(): void
    {
        $repositoryFile = $this->findGeneratedFile('/app/repository/demo/GenArticleRepository.php');
        $original = (string) file_get_contents($repositoryFile);
        $mutated = $this->insertStatementBeforeFinalBrace(
            $original,
            "\n    public function ctxCheckInjectedViolation(): array\n    {\n        return array(1, 2, 3);\n    }\n"
        );
        file_put_contents($repositoryFile, $mutated);

        $configPath = self::$tmpRoot . '/.php-cs-fixer.negative.php';
        $finderTarget = var_export(self::$tmpRoot . '/server/app', true);
        file_put_contents($configPath, <<<PHP
            <?php

            \$finder = PhpCsFixer\\Finder::create()->in({$finderTarget});

            return (new PhpCsFixer\\Config())
                ->setRules([
                    '@PSR12' => true,
                    'array_syntax' => ['syntax' => 'short'],
                    'declare_strict_types' => true,
                    'no_unused_imports' => true,
                    'ordered_imports' => ['sort_algorithm' => 'alpha'],
                ])
                ->setRiskyAllowed(true)
                ->setFinder(\$finder);
            PHP);

        try {
            exec(sprintf(
                'cd %s && vendor/bin/php-cs-fixer fix --config=%s --dry-run --diff --path-mode=intersection -- %s 2>&1',
                escapeshellarg(base_path()),
                escapeshellarg($configPath),
                escapeshellarg($repositoryFile)
            ), $output, $exitCode);
            $text = implode("\n", $output);

            $this->assertNotSame(0, $exitCode, "注入长数组语法之后 cs-fixer 必须变红。实际输出：\n{$text}");
            $this->assertStringContainsString('GenArticleRepository.php', $text, "报错文本没有点名被注入违规的那个文件：\n{$text}");
            $this->assertStringContainsString('array(1, 2, 3)', $text, "diff 里看不到被注入的长数组语法，可能是别的原因导致的失败：\n{$text}");
        } finally {
            file_put_contents($repositoryFile, $original);
            $this->assertSame($original, (string) file_get_contents($repositoryFile), '注入的违规必须清理干净，不能残留污染后续用例');
        }
    }

    /** phpstan level 6：往生成的 Model 追加一个明确的返回类型错误，断言必须变红并点名文件与方法名。 */
    public function test_phpstan_catches_an_injected_type_error(): void
    {
        $modelFile = $this->findGeneratedFile('/app/model/demo/GenArticle.php');
        $original = (string) file_get_contents($modelFile);
        $mutated = $this->insertStatementBeforeFinalBrace(
            $original,
            "\n    public function ctxCheckInjectedViolation(): int\n    {\n        return 'not-an-int';\n    }\n"
        );
        file_put_contents($modelFile, $mutated);

        $neonPath = self::$tmpRoot . '/phpstan-negative.neon';
        $neon = "parameters:\n"
            . "    level: 6\n"
            . "    paths:\n"
            . '        - "' . addslashes(self::$tmpRoot . '/server/app') . "\"\n"
            . "    bootstrapFiles:\n"
            . '        - "' . addslashes(base_path() . '/vendor/autoload.php') . "\"\n"
            . "    treatPhpDocTypesAsCertain: false\n"
            . "    universalObjectCratesClasses:\n"
            . "        - Webman\\Http\\Request\n";
        file_put_contents($neonPath, $neon);

        try {
            exec(sprintf(
                'cd %s && vendor/bin/phpstan analyse -c %s --memory-limit=1G --no-progress 2>&1',
                escapeshellarg(base_path()),
                escapeshellarg($neonPath)
            ), $output, $exitCode);
            $text = implode("\n", $output);

            $this->assertNotSame(0, $exitCode, "注入返回类型错误之后 phpstan 必须变红。实际输出：\n{$text}");
            $this->assertStringContainsString('GenArticle.php', $text, "报错文本没有点名被注入违规的那个文件：\n{$text}");
            $this->assertStringContainsString('ctxCheckInjectedViolation', $text, "报错文本没有点名被注入违规的那个方法，可能是别的原因导致的失败：\n{$text}");
        } finally {
            file_put_contents($modelFile, $original);
            $this->assertSame($original, (string) file_get_contents($modelFile), '注入的违规必须清理干净，不能残留污染后续用例');
        }
    }

    /** 在 self::$phpFiles（已落盘的绝对路径）里找一个按后缀匹配的文件，找不到就让用例直接失败。 */
    private function findGeneratedFile(string $suffix): string
    {
        foreach (self::$phpFiles as $file) {
            if (str_ends_with($file, $suffix)) {
                return $file;
            }
        }
        $this->fail("生成产物里没有找到后缀为 {$suffix} 的文件，先检查生成器是否还产出这个文件");
    }

    /** 把一段语句插进类文件最后一个右花括号之前——用于往合法的生成产物里注入一个新方法当作违规样本。 */
    private function insertStatementBeforeFinalBrace(string $content, string $statement): string
    {
        $trimmed = rtrim($content);
        $lastBrace = strrpos($trimmed, '}');
        $this->assertNotFalse($lastBrace, '生成产物里找不到类的收尾右花括号，注入点选取失败');

        return substr($trimmed, 0, $lastBrace) . $statement . "}\n";
    }
}
