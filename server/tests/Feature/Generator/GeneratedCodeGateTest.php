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
}
