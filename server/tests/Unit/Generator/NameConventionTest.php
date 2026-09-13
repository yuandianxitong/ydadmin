<?php

declare(strict_types=1);

namespace tests\Unit\Generator;

use core\generator\NameConvention;
use tests\TestCase;

final class NameConventionTest extends TestCase
{
    public function test_model_from_table_strips_trailing_plural_s(): void
    {
        $convention = new NameConvention();

        $this->assertSame('GenArticle', $convention->modelFromTable('gen_articles'));
        $this->assertSame('Menu', $convention->modelFromTable('menus'));
    }

    public function test_model_from_table_strips_a_non_empty_prefix_first(): void
    {
        $convention = new NameConvention('wp_');

        $this->assertSame('GenArticle', $convention->modelFromTable('wp_gen_articles'));
    }

    public function test_model_from_table_default_prefix_is_empty_so_nothing_is_stripped(): void
    {
        // 默认构造不传前缀；表名恰好以某串开头也不会被当成前缀去掉（底稿 §2：当前 DB_PREFIX 为空）
        $convention = new NameConvention();

        $this->assertSame('WpGenArticle', $convention->modelFromTable('wp_gen_article'));
    }

    public function test_model_from_table_leaves_an_already_singular_table_name_untouched(): void
    {
        $convention = new NameConvention();

        $this->assertSame('Article', $convention->modelFromTable('article'));
    }

    public function test_model_from_table_does_not_mangle_a_word_ending_in_double_s(): void
    {
        // address 以单个 s 结尾但不是复数；去掉会变成不存在的词 addres，用「结尾是不是 ss」挡住
        $convention = new NameConvention();

        $this->assertSame('Address', $convention->modelFromTable('address'));
    }

    public function test_model_from_table_prefix_not_matching_the_table_is_left_alone(): void
    {
        $convention = new NameConvention('wp_');

        $this->assertSame('Menu', $convention->modelFromTable('menus'));
    }

    public function test_kebab_inserts_a_hyphen_before_each_inner_uppercase_letter(): void
    {
        $convention = new NameConvention();

        $this->assertSame('article-category', $convention->kebab('ArticleCategory'));
        $this->assertSame('gen-article', $convention->kebab('GenArticle'));
    }

    public function test_snake_inserts_an_underscore_before_each_inner_uppercase_letter(): void
    {
        $convention = new NameConvention();

        $this->assertSame('article_category', $convention->snake('ArticleCategory'));
        $this->assertSame('gen_article', $convention->snake('GenArticle'));
    }

    public function test_bare_table_name_strips_the_prefix_for_use_as_eloquent_table_property(): void
    {
        // 非空 DB_PREFIX 下，Eloquent 连接层会再给 $table 套一次前缀，模板必须写裸表名，
        // 否则前缀会被套两次。
        $convention = new NameConvention('wp_');

        $this->assertSame('gen_articles', $convention->bareTableName('wp_gen_articles'));
    }

    public function test_bare_table_name_is_unchanged_when_prefix_is_empty_or_not_matching(): void
    {
        $default = new NameConvention();
        $this->assertSame('gen_articles', $default->bareTableName('gen_articles'));

        $prefixed = new NameConvention('wp_');
        $this->assertSame('menus', $prefixed->bareTableName('menus'));
    }

    public function test_bare_table_name_does_not_singularize_unlike_model_from_table(): void
    {
        // bareTableName 是给 $table 用的物理表名，必须保留复数形态；单复数还原只在 modelFromTable() 里发生。
        $convention = new NameConvention();

        $this->assertSame('gen_articles', $convention->bareTableName('gen_articles'));
        $this->assertSame('GenArticle', $convention->modelFromTable('gen_articles'));
    }
}
