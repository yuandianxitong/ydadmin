<?php

declare(strict_types=1);

namespace tests\Support;

use app\repository\system\SchemaRepository;
use core\generator\GeneratorRequest;
use core\generator\ModuleBlueprint;
use core\generator\TableDefinition;
use core\generator\TemplateRenderer;
use core\generator\TypeInference;
use support\Db;

/**
 * 代码生成器的黄金夹具（spec §11.1）。
 *
 * 全部模板共用同一张表 gen_articles：它刻意覆盖了 status / deleted_at / created_by + dept_id /
 * enum / text / decimal / 图片列名 / unique 索引 / 可空列 / 日期列这些整段分支。表建在测试库，
 * 由 createGoldenTable() 建、dropGoldenTable() 删，不依赖任何安装脚本，也不留残留。
 *
 * 模块名固定 demo：既有 resource/lang/{locale}/business.php 已经被手写模块占着，夹具再叫 business
 * 就会在语言包那一产物上和既有文件同名。
 */
trait GeneratorFixture
{
    public const GOLDEN_TABLE = 'gen_articles';

    public const GOLDEN_COMMENT = '生成器夹具表';

    public const GOLDEN_MODULE = 'demo';

    public const GOLDEN_MODEL = 'GenArticle';

    public static function createGoldenTable(): void
    {
        self::dropGoldenTable();
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

    public static function dropGoldenTable(): void
    {
        Db::statement('DROP TABLE IF EXISTS `gen_articles`');
    }

    public const GOLDEN_TABLE_2 = 'gen_categories';

    public const GOLDEN_COMMENT_2 = '生成器夹具表二（无状态列/图片列/创建人列）';

    public const GOLDEN_MODULE_2 = 'catalog';

    public const GOLDEN_MODEL_2 = 'GenCategory';

    /**
     * 第二张黄金夹具表（M2a 延续清单 #5）：刻意不含 status / 图片列名提示 / created_by，
     * 用来跑到 gen_articles 从未跑过的六类分支——hasStatus=false、hasImage=false、
     * dataScoped=false、boolean（tinyint(1)）、json、email（contact_email 命名列）。
     */
    public static function createGoldenTable2(): void
    {
        self::dropGoldenTable2();
        Db::statement(<<<'SQL'
            CREATE TABLE `gen_categories` (
              `id` int unsigned NOT NULL AUTO_INCREMENT,
              `name` varchar(100) NOT NULL COMMENT '名称',
              `is_featured` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否精选',
              `settings` json DEFAULT NULL COMMENT '扩展配置',
              `contact_email` varchar(100) DEFAULT NULL COMMENT '联系邮箱',
              `sort` int NOT NULL DEFAULT '0' COMMENT '排序',
              `created_at` datetime DEFAULT NULL,
              `updated_at` datetime DEFAULT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='生成器夹具表二（无状态列/图片列/创建人列）'
            SQL);
    }

    public static function dropGoldenTable2(): void
    {
        Db::statement('DROP TABLE IF EXISTS `gen_categories`');
    }

    /** 夹具表二的模块蓝图，不需要 overrides（第一张表已覆盖 override 分支，见 goldenBlueprint()）。 */
    protected function goldenBlueprintSecondary(): ModuleBlueprint
    {
        $inference = new TypeInference();
        $columns = [];
        foreach ((new SchemaRepository())->listColumns(self::GOLDEN_TABLE_2) as $raw) {
            $columns[] = $inference->describe($raw);
        }

        return new ModuleBlueprint(
            new TableDefinition(self::GOLDEN_TABLE_2, self::GOLDEN_COMMENT_2, $columns),
            new GeneratorRequest(self::GOLDEN_TABLE_2, self::GOLDEN_MODULE_2, self::GOLDEN_MODEL_2, self::GOLDEN_COMMENT_2, []),
        );
    }

    /**
     * 渲染夹具表二模块的某一个产物，形状与 renderGolden() 一致。
     *
     * @return array{0: string, 1: string} [相对基准的落盘路径, 渲染内容]
     */
    protected function renderGoldenSecondary(string $key): array
    {
        $artifacts = $this->goldenBlueprintSecondary()->artifacts();
        $this->assertArrayHasKey($key, $artifacts, "夹具表二的蓝图里没有产物 {$key}");
        $artifact = $artifacts[$key];
        $renderer = new TemplateRenderer(base_path('core/generator/stubs'));

        return [$artifact['path'], $renderer->render($artifact['stub'], $artifact['vars'])];
    }

    /**
     * 夹具表的模块蓝图。
     *
     * $overrides 既喂给 GeneratorRequest，也当场套到 ColumnDescriptor 上：前者是 preview/generate 的
     * 真实入参形状，后者保证不论「谁来套 overrides」（蓝图还是服务层）这份夹具都给出同一套列，
     * 重复套用是幂等的（withOverrides 是整字段覆盖，不是累加）。
     *
     * @param array<string, array{form_type: string, searchable: bool, in_list: bool, in_form: bool}> $overrides 以列名为键
     */
    protected function goldenBlueprint(array $overrides = []): ModuleBlueprint
    {
        $inference = new TypeInference();
        $columns = [];
        foreach ((new SchemaRepository())->listColumns(self::GOLDEN_TABLE) as $raw) {
            $column = $inference->describe($raw);
            $override = $overrides[$column->name] ?? null;
            if ($override !== null) {
                $column = $column->withOverrides(
                    $override['form_type'],
                    $override['searchable'],
                    $override['in_list'],
                    $override['in_form'],
                );
            }
            $columns[] = $column;
        }

        return new ModuleBlueprint(
            new TableDefinition(self::GOLDEN_TABLE, self::GOLDEN_COMMENT, $columns),
            new GeneratorRequest(self::GOLDEN_TABLE, self::GOLDEN_MODULE, self::GOLDEN_MODEL, self::GOLDEN_COMMENT, $overrides),
        );
    }

    /**
     * 渲染夹具模块的某一个产物。只渲染点名的那一个：模板是分任务落盘的，
     * 整批渲染会让还没写模板的产物把别人的用例一起带红。
     *
     * 返回的路径直接就是夹具树里的相对路径（见 GoldenFile）——这是「夹具树 == 生成树」的兑现处。
     *
     * @param array<string, array{form_type: string, searchable: bool, in_list: bool, in_form: bool}> $overrides
     * @return array{0: string, 1: string} [相对基准的落盘路径, 渲染内容]
     */
    protected function renderGolden(string $key, array $overrides = []): array
    {
        $artifacts = $this->goldenBlueprint($overrides)->artifacts();
        $this->assertArrayHasKey($key, $artifacts, "蓝图里没有产物 {$key}");
        $artifact = $artifacts[$key];
        $renderer = new TemplateRenderer(base_path('core/generator/stubs'));

        return [$artifact['path'], $renderer->render($artifact['stub'], $artifact['vars'])];
    }
}
