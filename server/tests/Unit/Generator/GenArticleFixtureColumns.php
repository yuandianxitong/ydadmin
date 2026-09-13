<?php

declare(strict_types=1);

namespace tests\Unit\Generator;

use core\generator\ColumnDescriptor;
use core\generator\TableDefinition;
use core\generator\TypeInference;

/**
 * gen_articles 黄金夹具表（底稿 §5）的列定义，供本任务（route/lang/menu 三个 stub）的测试直接
 * 构造 TableDefinition，不依赖真实数据库连接、不依赖 SchemaRepository。
 *
 * 若 core/generator 其它任务已经提供了等价的共用测试夹具（比如从真实建表 SQL 经
 * SchemaRepository::listColumns() + TypeInference::describe() 反查出来的版本），三个 stub 测试
 * 类应当改用那个共用版本、删掉这个 trait——这里只是保证 Task 6 的测试能独立验证，不依赖其它任务
 * 是否已经完成。
 *
 * dept_id 的 in_form 是 false（第三轮裁定）：它和 created_by 一样是归属列，由系统写入、不进表单
 * 白名单，因此不会出现在 $formColumns 里，也不会产生任何语言键。
 */
trait GenArticleFixtureColumns
{
    /** @return list<ColumnDescriptor> */
    private static function genArticleColumns(): array
    {
        return [
            new ColumnDescriptor('id', 'integer', 'int unsigned', false, null, '', 'PRI', 'auto_increment', 'number', false, true, false, []),
            new ColumnDescriptor('title', 'string', 'varchar(200)', false, null, '标题', '', '', 'input', true, true, true, []),
            new ColumnDescriptor('summary', 'string', 'varchar(500)', true, null, '摘要', '', '', 'input', false, true, true, []),
            new ColumnDescriptor('content', 'text', 'longtext', true, null, '正文', '', '', 'textarea', false, false, true, []),
            new ColumnDescriptor('cover_image', 'string', 'varchar(255)', true, null, '封面图', '', '', 'image', false, true, true, []),
            new ColumnDescriptor('category', 'enum', "enum('news','tech','life')", false, 'news', '分类', '', '', 'select', false, true, true, ['news', 'tech', 'life']),
            new ColumnDescriptor('price', 'decimal', 'decimal(10,2)', false, '0.00', '价格', '', '', 'number', false, true, true, []),
            new ColumnDescriptor('view_count', 'integer', 'int unsigned', false, '0', '浏览量', '', '', 'number', false, true, true, []),
            new ColumnDescriptor('slug', 'string', 'varchar(100)', false, null, '别名', 'UNI', '', 'input', false, true, true, []),
            new ColumnDescriptor('published_at', 'datetime', 'datetime', true, null, '发布时间', '', '', 'datepicker', false, true, true, []),
            new ColumnDescriptor('status', 'integer', 'tinyint', false, '1', '状态', 'MUL', '', 'switch', true, true, true, []),
            new ColumnDescriptor('sort', 'integer', 'int', false, '0', '排序', '', '', 'number', false, true, true, []),
            new ColumnDescriptor('created_by', 'integer', 'int unsigned', true, null, '', '', '', 'number', false, true, false, []),
            new ColumnDescriptor('dept_id', 'integer', 'int unsigned', true, null, '', '', '', 'number', false, true, false, []),
            new ColumnDescriptor('created_at', 'datetime', 'datetime', true, null, '', '', '', 'datepicker', false, true, false, []),
            new ColumnDescriptor('updated_at', 'datetime', 'datetime', true, null, '', '', '', 'datepicker', false, true, false, []),
            new ColumnDescriptor('deleted_at', 'datetime', 'datetime', true, null, '', '', '', 'datepicker', false, false, false, []),
        ];
    }

    private static function genArticleTable(): TableDefinition
    {
        return new TableDefinition('gen_articles', '生成器夹具表', self::genArticleColumns());
    }

    /** @return list<ColumnDescriptor> */
    private static function genArticleFormColumns(): array
    {
        return array_values(array_filter(self::genArticleColumns(), static fn (ColumnDescriptor $c): bool => $c->inForm));
    }

    /** @return array<string, mixed> */
    private static function genArticleVars(string $locale = 'zh_CN'): array
    {
        $columns = self::genArticleColumns();

        return [
            'module' => 'demo',
            'model' => 'GenArticle',
            'modelSnake' => 'gen_article',
            'modelKebab' => 'gen-article',
            'tableName' => 'gen_articles',
            'tableComment' => '生成器夹具表',
            'table' => self::genArticleTable(),
            'columns' => $columns,
            'formColumns' => self::genArticleFormColumns(),
            'listColumns' => array_values(array_filter($columns, static fn (ColumnDescriptor $c): bool => $c->inList)),
            'searchColumns' => array_values(array_filter($columns, static fn (ColumnDescriptor $c): bool => $c->searchable)),
            'hasStatus' => true,
            'softDeletes' => true,
            'dataScoped' => true,
            'creatorColumn' => 'created_by',
            'deptColumn' => 'dept_id',
            'uniqueColumns' => ['slug'],
            'primaryKey' => 'id',
            'inference' => new TypeInference(),
            'locale' => $locale,
        ];
    }
}
