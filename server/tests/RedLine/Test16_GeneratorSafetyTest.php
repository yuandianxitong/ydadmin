<?php

declare(strict_types=1);

namespace tests\RedLine;

use support\Db;
use tests\Support\ApiTestCase;
use tests\Support\ConfigOverride;
use tests\Support\GeneratorFixture;

/**
 * 红线 Test16：代码生成器是「把任意文件写到磁盘」的能力（spec §9）。
 *
 * 为什么这是红线：module_name 与 model_name 会直接变成文件路径与 PHP 命名空间，而前端对它们
 * 不做任何校验（自由文本框）。一个 ../.. 能把 .php 写到 public/ 甚至仓库外；表名不做白名单比对
 * 就会被拼进 SHOW FULL COLUMNS；生产环境不关掉写操作，则 system.generator.generate 这个权限点
 * 等价于远程写代码。三条都必须 fail closed，且失败时磁盘一个字节都不许动。
 *
 * 本用例全程只发非法请求——没有任何一次合法 generate，所以真实的 app/ 与 admin/ 从头到尾不该有变化。
 */
final class Test16_GeneratorSafetyTest extends ApiTestCase
{
    use ConfigOverride;
    use GeneratorFixture;

    private const PREVIEW = '/adminapi/system/generator/preview';

    private const GENERATE = '/adminapi/system/generator/generate';

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
        // 红线判定不能依赖开发机 .env 里 APP_DEBUG 恰好是什么
        $this->overrideConfig('app.debug', true);
    }

    protected function tearDown(): void
    {
        $this->restoreConfig();
        parent::tearDown();
    }

    public function test_module_name_traversal_samples_are_all_rejected_without_touching_the_disk(): void
    {
        $admin = $this->actingAsAdmin('super'); // 超管也不例外
        $before = $this->snapshot();

        $samples = ['../x', '..\\x', '/etc/x', '', '中文', str_repeat('a', 64), 'demo/../../etc', 'Business', 'bu-si'];
        foreach ($samples as $sample) {
            foreach ([self::PREVIEW, self::GENERATE] as $uri) {
                $response = $this->post($uri, $this->payload(module: $sample), $admin->token);
                $response->assertCode(422);
                $this->assertArrayHasKey(
                    'module_name',
                    (array) ($response->data()['errors'] ?? []),
                    "module_name={$sample} 必须以 422 被拒并给出字段级错误（{$uri}）"
                );
            }
        }

        $this->assertSame($before, $this->snapshot(), '被拒绝的请求不得在磁盘上产生任何文件');
    }

    public function test_model_name_traversal_samples_are_all_rejected_without_touching_the_disk(): void
    {
        $admin = $this->actingAsAdmin('super');
        $before = $this->snapshot();

        $samples = ['../X', '..\\X', '/etc/X', '', '模型', str_repeat('A', 64), 'Business/../../Etc', 'genArticle', 'Gen_Article'];
        foreach ($samples as $sample) {
            foreach ([self::PREVIEW, self::GENERATE] as $uri) {
                $response = $this->post($uri, $this->payload(model: $sample), $admin->token);
                $response->assertCode(422);
                $this->assertArrayHasKey(
                    'model_name',
                    (array) ($response->data()['errors'] ?? []),
                    "model_name={$sample} 必须以 422 被拒并给出字段级错误（{$uri}）"
                );
            }
        }

        $this->assertSame($before, $this->snapshot(), '被拒绝的请求不得在磁盘上产生任何文件');
    }

    /**
     * 保留模块名必须被拒。生成的语言包落在 resource/lang/{locale}/{module}.php，撞上仓库现有的
     * 语言分组，该产物就会被判「已存在」跳过，于是生成代码里所有 lang() 原样回显 key，既不报错
     * 也无从排查。而前端模块名的默认值恰好就是 business（generator/index.vue 里硬编码），
     * 用户不填直接下一步就会踩——这条不是理论风险，是默认路径上的坑。
     */
    public function test_module_names_that_collide_with_existing_language_groups_are_rejected(): void
    {
        $admin = $this->actingAsAdmin('super');
        $before = $this->snapshot();

        foreach (['admin_log', 'auth', 'business', 'messages', 'validation', 'generator', 'apidoc'] as $reserved) {
            foreach ([self::PREVIEW, self::GENERATE] as $uri) {
                $response = $this->post($uri, $this->payload(module: $reserved), $admin->token);
                $response->assertCode(422);
                $errors = (array) ($response->data()['errors'] ?? []);
                $this->assertSame(
                    lang('generator.module_name_reserved'),
                    (string) ($errors['module_name'] ?? ''),
                    "module_name={$reserved} 必须以保留字理由被拒（{$uri}）"
                );
            }
        }

        $this->assertSame($before, $this->snapshot(), '被拒绝的请求不得在磁盘上产生任何文件');
    }

    /** spec §9.2：表名必须与 listTables() 的结果逐字比对，命中才继续。 */
    public function test_tables_outside_the_whitelist_are_rejected(): void
    {
        $admin = $this->actingAsAdmin('super');
        $before = $this->snapshot();

        $samples = ['no_such_table', 'gen_articles`', 'gen_articles WHERE 1', 'information_schema.tables', 'gen_articles; DROP TABLE gen_articles'];
        foreach ($samples as $sample) {
            foreach ([self::PREVIEW, self::GENERATE] as $uri) {
                $response = $this->post($uri, $this->payload(table: $sample), $admin->token);
                $this->assertContains($response->code(), [400, 422], "table_name={$sample} 必须被拒（{$uri}）");
                if ($response->code() === 400) {
                    $this->assertSame(lang('generator.table_not_found'), $response->message());
                }
            }
        }

        // 夹具表还在、还是空的：没有任何一条样本被当成 SQL 执行（表被 DROP 了这行会直接抛异常）
        $this->assertSame(0, Db::table(self::GOLDEN_TABLE)->count(), '夹具表必须原封不动地还在');
        $this->assertSame($before, $this->snapshot());
    }

    /** spec §4.5 / §9.3：生产环境两个写端点一律拒绝，且不渲染、不落盘；两个只读端点不受影响。 */
    public function test_preview_and_generate_are_disabled_when_debug_is_off(): void
    {
        $admin = $this->actingAsAdmin('super');
        $this->overrideConfig('app.debug', false);
        $before = $this->snapshot();

        foreach ([self::PREVIEW, self::GENERATE] as $uri) {
            $response = $this->post($uri, $this->payload(), $admin->token)->assertCode(400);
            $this->assertSame(lang('generator.disabled_in_production'), $response->message(), "{$uri} 在生产环境必须被拒");
        }

        $this->assertSame($before, $this->snapshot(), '生产禁用时一个字节都不许落盘');

        // 只读端点照常可用
        $this->get('/adminapi/system/generator/tables', [], $admin->token)->assertOk();
        $this->get('/adminapi/system/generator/columns', ['table' => self::GOLDEN_TABLE], $admin->token)->assertOk();
    }

    /** spec §9.4：客户端回传的 columns 只有四个可编辑字段被采信，其余一律以实时查表为准。 */
    public function test_only_the_four_editable_column_fields_are_trusted(): void
    {
        $admin = $this->actingAsAdmin('super');
        $before = $this->snapshot();

        // preview 不落盘，可以安全地用真实基准跑一次完整渲染
        $payload = $this->payload() + ['columns' => [
            // 谎报 name 之外的一切：类型、原始类型、注释、主键标记
            ['name' => 'title', 'type' => 'evil', 'raw_type' => 'varchar(99999)', 'nullable' => true,
                'default' => null, 'comment' => 'PWNED', 'key' => 'PRI', 'extra' => 'auto_increment',
                'form_type' => 'textarea', 'searchable' => false, 'in_list' => false, 'in_form' => true],
            // 表里不存在的列必须被忽略
            ['name' => 'not_a_column', 'type' => 'string', 'raw_type' => 'varchar(1)', 'nullable' => true,
                'default' => null, 'comment' => 'INJECTED', 'key' => '', 'extra' => '',
                'form_type' => 'input', 'searchable' => true, 'in_list' => true, 'in_form' => true],
        ]];

        $data = (array) $this->post(self::PREVIEW, $payload, $admin->token)->assertOk()->data();
        $all = implode("\n", array_map(static fn (array $file): string => (string) $file['content'], $data));

        $this->assertStringNotContainsString('PWNED', $all, '客户端传的 comment 不得进入产物');
        $this->assertStringNotContainsString('INJECTED', $all, '表里不存在的列必须被忽略');
        $this->assertStringNotContainsString('not_a_column', $all);
        $this->assertStringNotContainsString('99999', $all, '客户端传的 raw_type 不得影响校验规则');
        $this->assertStringContainsString('max:200', $all, '长度上限必须来自实时查表的 varchar(200)');

        // 四个可编辑字段确实生效了：title 的控件被改成 textarea，且移出了列表页表格
        $this->assertMatchesRegularExpression(
            '/form\.title[^\n]*type="textarea"/',
            (string) $data['form']['content'],
            'form_type 覆盖必须生效：title 应当渲染成 textarea 控件'
        );
        $this->assertStringNotContainsString('prop="title"', (string) $data['page']['content'], 'in_list=false 必须把 title 移出列表页表格');

        $this->assertSame($before, $this->snapshot(), 'preview 永远不落盘');
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function payload(string $module = self::GOLDEN_MODULE, string $model = self::GOLDEN_MODEL, string $table = self::GOLDEN_TABLE): array
    {
        return ['table_name' => $table, 'module_name' => $module, 'model_name' => $model, 'table_comment' => self::GOLDEN_COMMENT];
    }

    /** @return list<string> 穿越样本会落到的目录的文件快照 */
    private function snapshot(): array
    {
        $server = base_path();
        $repo = dirname($server);
        $paths = [];
        foreach ([
            $server . '/app',
            $server . '/config',
            $server . '/resource/lang',
            $server . '/database',
            $server . '/public',
            $repo . '/admin/src/api',
            $repo . '/admin/src/views',
        ] as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($iterator as $file) {
                $paths[] = (string) $file->getPathname();
            }
        }
        // ../ 与 /etc/ 这类样本会落到仓库根甚至它的上一级，各扫一层
        foreach ([$repo, dirname($repo)] as $dir) {
            foreach ((array) (glob($dir . '/*') ?: []) as $entry) {
                $paths[] = (string) $entry;
            }
        }
        sort($paths);

        return $paths;
    }
}
