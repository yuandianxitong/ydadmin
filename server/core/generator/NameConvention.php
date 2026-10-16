<?php

declare(strict_types=1);

namespace core\generator;

/**
 * 命名规则：表名 → 模型名（大驼峰）、模型名 → kebab-case / snake_case、表名 → 裸表名
 * （spec §5.2、§5.5）。纯字符串规则，无状态。
 */
final class NameConvention
{
    public function __construct(private readonly string $tablePrefix = '')
    {
    }

    /**
     * gen_articles → GenArticle：先去前缀（`$tablePrefix` 非空且表名以它开头才去），
     * 再去掉末尾单个复数 s，最后按下划线切分转大驼峰。
     *
     * 只处理最简单的英语复数（末尾单个 s，且不是 ...ss 结尾，如 address）；不识别
     * category → categories 这类 y→ies、box → boxes 这类 -es 等不规则复数——生成器的
     * 表名以 snake_case 单词为主，这条规则覆盖主流写法即可，碰不到的表名用
     * `make:crud --model=` 手动指定（spec §5.5）。
     */
    public function modelFromTable(string $table): string
    {
        $stem = $this->singularize($this->stripPrefix($table));

        $segments = array_filter(explode('_', $stem), static fn (string $segment): bool => $segment !== '');

        return implode('', array_map(
            static fn (string $segment): string => ucfirst(strtolower($segment)),
            $segments,
        ));
    }

    /** ArticleCategory → article-category */
    public function kebab(string $model): string
    {
        return str_replace('_', '-', $this->snake($model));
    }

    /** ArticleCategory → article_category */
    public function snake(string $model): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $model));
    }

    /**
     * 去掉表前缀后的裸表名，供模板写 Model::$table 用。非空 DB_PREFIX 下，Eloquent 的
     * 连接层还会再给 $table 套一次前缀，所以模板不能直接写物理表名（会被双重加前缀），
     * 必须写这个。跟 modelFromTable() 不同：这里不做单复数还原，保留表名本来的形态。
     */
    public function bareTableName(string $table): string
    {
        return $this->stripPrefix($table);
    }

    private function stripPrefix(string $table): string
    {
        if ($this->tablePrefix !== '' && str_starts_with($table, $this->tablePrefix)) {
            return substr($table, strlen($this->tablePrefix));
        }

        return $table;
    }

    private function singularize(string $stem): string
    {
        if ($stem === '' || !str_ends_with($stem, 's') || str_ends_with($stem, 'ss')) {
            return $stem;
        }

        return substr($stem, 0, -1);
    }
}
