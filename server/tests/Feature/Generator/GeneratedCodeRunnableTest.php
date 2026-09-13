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
use Webman\Http\Request;

/**
 * spec §11.5：门禁测试只证明产物「能过静态检查」，这与「能工作」是两回事，而 M7 依赖的是后者。
 * 把黄金夹具模块生成到 server/runtime/ 下一个纳入 Composer 自动加载的临时目录，直接实例化
 * 生成的 Controller 调用其方法，断言真实返回的数据结构符合契约——不走 HTTP、不需要 reload。
 *
 * 六个基础路由（index/store/show/update/delete/batchDelete）的方法名由 spec §8 的路由模板逐字
 * 给定，可以放心依赖；status 端点的控制器方法名叫 status（依据仓库既有写法：AdminController、
 * RoleController 的 PUT /{id}/status 对应的控制器方法都叫 status——与 spec §7.3 提到的
 * Service::updateStatus() 不是同一个名字，Service 层才叫 updateStatus，Controller 层是 status）。
 *
 * 还有一条不变量测试（test_generated_language_keys_match_controller_referenced_keys）单独成立
 * 的理由：lang.stub.php 与 controller.stub.php 按设计都迭代同一个 TypeInference 的规则推断结果，
 * 运行时理论上不可能分叉，但两份模板是分开写的纯文本文件，人手写的时候完全可能分叉——控制器
 * messages() 引用了语言包里不存在的键、或者语言包里躺着一堆没人引用的键，这类错误不会让任何
 * 断言 HTTP 状态码/code 的测试变红：接口照常返回 422，只是 data.errors 里的文案变成一串原始
 * key，得有人点开界面才会发现。这条测试直接从生成的产物文本里用正则抠出两边的键集合比对。
 */
final class GeneratedCodeRunnableTest extends TestCase
{
    private const MODULE = 'demo';
    private const MODEL = 'GenArticle';

    private static string $tmpRoot;
    private static string $langZhContent = '';
    private static string $controllerContent = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createFixtureTable();
        self::$tmpRoot = base_path('runtime') . '/generator-runnable-' . bin2hex(random_bytes(6));
        self::generateModule(self::$tmpRoot);
        self::registerAutoload(self::$tmpRoot . '/server');
    }

    public static function tearDownAfterClass(): void
    {
        Db::statement('DROP TABLE IF EXISTS `gen_articles`');
        self::removeDirectory(self::$tmpRoot);
        parent::tearDownAfterClass();
    }

    protected function tearDown(): void
    {
        Db::table('gen_articles')->truncate();
        parent::tearDown();
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
        $request = new GeneratorRequest('gen_articles', self::MODULE, self::MODEL, '生成器夹具表', []);

        // preview() 按 spec §5.3 与 generate() 共用同一条渲染路径，返回的 content 与磁盘上落盘的
        // 字节一致（这一点由 Task 8 的「预览即所得」测试钉死，本类不重复验证）；这里用它是因为它
        // 按 key 返回 {lang_zh: {...}, controller: {...}}，不用像 generate() 的 files 列表那样
        // 再去猜哪条 path 对应哪个产物。
        $preview = $service->preview($request);
        self::$langZhContent = (string) $preview['lang_zh']['content'];
        self::$controllerContent = (string) $preview['controller']['content'];

        $service->generate($request);
    }

    /**
     * 把临时目录纳入 Composer 的自动加载：给 app\ 前缀多注册一个候选目录，找不到时才会用它——
     * 真实业务模块（server/app 下的文件）永远优先命中，不会被临时目录里的同名类顶替。
     *
     * 还要清 Composer\Autoload\ClassLoader 私有的 $missingClasses 缓存：它的 findFile() 一旦
     * 对某个类名解析失败就把这个类名记进去（vendor/composer/ClassLoader.php），此后同一个类名
     * 再也不会真的去找文件，直接返回失败——不管后面有没有新注册 PSR4 路径。本仓库
     * tests/Unit/Generator/RouteStubTest.php 会在本类之前对同一个类名 GenArticleController
     * 做 is_callable() 检查（生成的路由文件里的回调，那时候还没注册这份临时 PSR4 映射，检查
     * 必然失败），一旦跑在本类前面就会把这个类名永久钉进 $missingClasses，
     * 下面的 addPsr4() 从此再也不会被真正咨询到——完整跑 `composer test` 时才会踩中
     * （单独跑本测试类不会，因为没有别的测试先碰过这个类名），必须在这里把整个缓存清空
     * （不只清这一个类名：谁都不知道后面还有没有别的生成模块类名也被别的测试提前问过）。
     */
    private static function registerAutoload(string $tmpServerRoot): void
    {
        $loader = require base_path() . '/vendor/autoload.php';
        $loader->addPsr4('app\\', $tmpServerRoot . '/app');
        (new \ReflectionProperty($loader, 'missingClasses'))->setValue($loader, []);
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

    private function controllerClass(): string
    {
        return 'app\\adminapi\\controller\\' . self::MODULE . '\\' . self::MODEL . 'Controller';
    }

    private function request(string $method, string $uri, string $body = ''): Request
    {
        $headers = "Host: localhost\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n";

        return new Request("{$method} {$uri} HTTP/1.1\r\n{$headers}\r\n{$body}");
    }

    public function test_generated_index_returns_contract_shaped_pagination(): void
    {
        $now = date('Y-m-d H:i:s');
        Db::table('gen_articles')->insert([
            ['title' => '第一篇', 'slug' => 'first-' . uniqid(), 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['title' => '第二篇', 'slug' => 'second-' . uniqid(), 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['title' => '第三篇', 'slug' => 'third-' . uniqid(), 'status' => 0, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $controller = Container::get($this->controllerClass());
        $response = $controller->index($this->request('GET', '/adminapi/demo/gen-article?page=1&limit=10'));
        $body = json_decode((string) $response->rawBody(), true);

        $this->assertSame(200, $body['code'], (string) $response->rawBody());
        $this->assertSame(['list', 'pagination'], array_keys($body['data']));
        $this->assertSame(['current_page', 'per_page', 'total', 'last_page'], array_keys($body['data']['pagination']));
        $this->assertSame(3, $body['data']['pagination']['total']);
        $this->assertCount(3, $body['data']['list']);
        $this->assertArrayHasKey('title', $body['data']['list'][0]);
        $this->assertArrayHasKey('status_text', $body['data']['list'][0], '有 status 列应生成 status_text 访问器');
    }

    public function test_generated_store_show_and_delete_round_trip(): void
    {
        $controller = Container::get($this->controllerClass());

        $payload = (string) json_encode(['title' => '新建文章', 'slug' => 'created-' . uniqid()], JSON_UNESCAPED_UNICODE);
        $storeResponse = $controller->store($this->request('POST', '/adminapi/demo/gen-article', $payload));
        $storeBody = json_decode((string) $storeResponse->rawBody(), true);
        $this->assertSame(200, $storeBody['code'], (string) $storeResponse->rawBody());
        $id = (string) $storeBody['data']['id'];

        $showResponse = $controller->show($this->request('GET', "/adminapi/demo/gen-article/{$id}"), $id);
        $showBody = json_decode((string) $showResponse->rawBody(), true);
        $this->assertSame(200, $showBody['code']);
        $this->assertSame('新建文章', $showBody['data']['title']);

        $deleteResponse = $controller->delete($this->request('DELETE', "/adminapi/demo/gen-article/{$id}"), $id);
        $this->assertSame(200, json_decode((string) $deleteResponse->rawBody(), true)['code']);
        $this->assertNotNull(Db::table('gen_articles')->where('id', $id)->value('deleted_at'), '有 deleted_at 列应软删');
    }

    public function test_generated_status_endpoint_toggles_status(): void
    {
        $now = date('Y-m-d H:i:s');
        $slug = 'status-' . uniqid();
        Db::table('gen_articles')->insert(['title' => '状态切换', 'slug' => $slug, 'status' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $id = (string) Db::table('gen_articles')->where('slug', $slug)->value('id');

        $controller = Container::get($this->controllerClass());
        $payload = (string) json_encode(['status' => 0], JSON_UNESCAPED_UNICODE);
        $response = $controller->status($this->request('PUT', "/adminapi/demo/gen-article/{$id}/status", $payload), $id);
        $body = json_decode((string) $response->rawBody(), true);

        $this->assertSame(200, $body['code'], (string) $response->rawBody());
        $this->assertSame(0, (int) Db::table('gen_articles')->where('id', $id)->value('status'));
    }

    /**
     * lang.stub.php 与 controller.stub.php 按设计都迭代同一个 TypeInference::ruleTokens() 的
     * 推断结果，来产出「字段级」校验消息键（{modelSnake}_{字段}_{token}）：这部分运行时不应该
     * 分叉，也是本条测试真正要钉住的不变量。
     *
     * 语言包产物里另外还有三类键，两份模板设计上就不共享、天然不会出现在控制器 messages() 里，
     * 提前列出来是为了不把它们错判成「模板分叉」（look at core/generator/stubs/lang.stub.php /
     * service.stub.php 就能看到它们各自的落点）：
     *   - {modelSnake}_not_found：GenArticleService::find 系列方法直接 lang() 抛 BusinessException，
     *     不经过 Controller::validate()/messages()；
     *   - {modelSnake}_{唯一列}_exists：同样由 Service 层查重逻辑直接 lang()（spec 决策 12：
     *     唯一性不做成校验规则，改由 Service 查重 + 唯一索引异常兜底）；
     *   - {modelSnake}_ids_require / {modelSnake}_ids_integer：批量删除的消息数组是 Controller
     *     里内联在 batchDelete() 方法体的字面量（"$data['ids']" 那段），不经过 messages()。
     *
     * 断言分两层：字段级键必须与 messages() 完全一致（多一个少一个都报）；语言包里出现的、
     * 既不在 messages() 也不在上面这份「已知非 messages() 键」清单里的键一律视为多余/分叉，
     * 必须报出来——这样以后模板新长出一个没人引用的键，或者 controller 引用了语言包没有的键，
     * 都逃不过这条测试。
     */
    public function test_generated_language_keys_match_controller_referenced_keys(): void
    {
        $langKeys = self::extractLangKeys(self::$langZhContent);
        $controllerKeys = self::extractControllerMessageKeys(self::$controllerContent, self::MODULE);

        $this->assertNotEmpty($langKeys, '语言包产物里一个键都没抠到，先检查上面的正则是否匹配 lang.stub.php 实际产出的格式');
        $this->assertNotEmpty($controllerKeys, 'messages() 里一个键都没抠到，先检查上面的正则是否匹配 controller.stub.php 实际产出的格式');

        // 黄金夹具表 gen_articles 固定只有一个唯一索引列 slug（uk_slug），与 GeneratorFixture /
        // GoldenModuleTest 里对 uniqueColumns() 的断言一致；不是从产物文本里推的，是这张固定表的已知事实。
        $modelSnake = 'gen_article';
        $knownNonMessageKeys = [
            "{$modelSnake}_not_found",
            "{$modelSnake}_slug_exists",
            "{$modelSnake}_ids_require",
            "{$modelSnake}_ids_integer",
        ];
        $missingKnownKeys = array_values(array_diff($knownNonMessageKeys, $langKeys));
        $this->assertSame(
            [],
            $missingKnownKeys,
            '语言包里缺了几个已知不经过 messages() 但确实该由 Service/batchDelete 直接引用的键，'
                . '说明 lang.stub.php 或本测试的清单本身过期了：' . implode(', ', $missingKnownKeys)
        );

        $fieldDrivenLangKeys = array_values(array_diff($langKeys, $knownNonMessageKeys));

        sort($fieldDrivenLangKeys);
        $sortedControllerKeys = $controllerKeys;
        sort($sortedControllerKeys);

        $missingInLang = array_values(array_diff($sortedControllerKeys, $fieldDrivenLangKeys));
        $unusedInLang = array_values(array_diff($fieldDrivenLangKeys, $sortedControllerKeys));

        $this->assertSame(
            [],
            $missingInLang,
            'controller messages() 引用了语言包里不存在的键：' . implode(', ', $missingInLang)
        );
        $this->assertSame(
            [],
            $unusedInLang,
            '语言包里有 controller messages() 从未引用过、也不在已知非 messages() 清单里的键'
                . '（多余，应清理或说明为什么保留）：' . implode(', ', $unusedInLang)
        );
    }

    /** 语言包产物是形如 `'gen_article_title_require' => '标题不能为空',` 的扁平数组，抠顶层键即可。
     *
     * @return list<string>
     */
    private static function extractLangKeys(string $content): array
    {
        preg_match_all("/'([a-zA-Z0-9_]+)'\s*=>/", $content, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * 控制器产物里的 messages() 形如：
     *   private function messages(): array
     *   {
     *       return [
     *           'title.required' => 'demo.gen_article_title_require',
     *           ...
     *       ];
     *   }
     * 先按「方法名到紧跟着的、缩进 4 个空格的右花括号」切出方法体（PSR12 的类方法体缩进就是
     * 4 个空格，数组内容缩进更深，不会提前撞上这个边界），再在方法体里抓 '{module}.{key}' 形式
     * 的值，只要 key 部分。
     *
     * @return list<string>
     */
    private static function extractControllerMessageKeys(string $content, string $module): array
    {
        if (preg_match('/private function messages\(\): array\s*\{(.*?)\n    \}/s', $content, $methodMatch) !== 1) {
            return [];
        }
        preg_match_all('/=>\s*\'' . preg_quote($module, '/') . '\.([a-zA-Z0-9_]+)\'/', $methodMatch[1], $keyMatches);

        return array_values(array_unique($keyMatches[1]));
    }
}
