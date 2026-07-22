<?php
declare(strict_types=1);

namespace core\database;

use PDO;
use PDOException;
use RuntimeException;

/**
 * SQL 执行器（零框架依赖）
 *
 * 统一处理 schema.sql / init.sql / updates 增量脚本的执行：
 * - 自动为裸表名套上数据表前缀（DDL/DML 均覆盖）
 * - 安全拆分多语句（不会在引号/反引号内被分号截断）
 * - 支持占位符替换（如 {{SITE_URL}}）
 *
 * 该类仅依赖 PDO 与字符串函数，可同时被：
 * - 独立安装程序 public/install/install.class.php（require 后直接使用）
 * - 框架内的 php think yd:update 升级命令
 * 复用，保证「安装」与「升级」的前缀处理行为完全一致。
 */
class SqlRunner
{
    public function __construct(
        private PDO $pdo,
        private string $prefix = ''
    ) {
        $this->prefix = trim($this->prefix);
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * 执行 SQL 文件
     *
     * @param array<string,string> $replacements 占位符 => 实际值
     */
    public function runFile(string $sqlFile, array $replacements = []): void
    {
        $sql = @file_get_contents($sqlFile);
        if ($sql === false) {
            throw new RuntimeException('SQL文件读取失败: ' . $sqlFile);
        }
        $this->runSql($sql, $replacements);
    }

    /**
     * 执行一段 SQL 文本
     *
     * @param array<string,string> $replacements 占位符 => 实际值
     */
    public function runSql(string $sql, array $replacements = []): void
    {
        foreach ($replacements as $placeholder => $value) {
            $sql = str_replace($placeholder, $value, $sql);
        }

        $sql = $this->applyTablePrefix($sql, $this->prefix);
        // 去除块注释 /* ... */
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

        // 去除行注释（-- 与 #）
        $lines = preg_split("/\r\n|\n|\r/", $sql);
        $filtered = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || strpos($trimmed, '--') === 0 || strpos($trimmed, '#') === 0) {
                continue;
            }
            $filtered[] = $line;
        }
        $sql = implode("\n", $filtered);

        $statements = $this->splitSqlStatements($sql);

        $i = 0;
        foreach ($statements as $statement) {
            $i++;
            if ($statement === '') {
                continue;
            }
            try {
                $this->pdo->exec($statement);
            } catch (PDOException $e) {
                $snippet = preg_replace('/\s+/', ' ', trim($statement));
                $snippet = mb_substr((string) $snippet, 0, 260, 'UTF-8');
                throw new RuntimeException('SQL执行失败（第 ' . $i . ' 条）：' . $e->getMessage() . '；SQL片段：' . $snippet);
            }
        }
    }

    /**
     * 按分号拆分多语句，跳过引号/双引号/反引号内的分号
     *
     * @return array<int,string>
     */
    public function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $inSingle = false;
        $inDouble = false;
        $inBacktick = false;
        $escape = false;

        $len = strlen($sql);
        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];

            if ($escape) {
                $buffer .= $ch;
                $escape = false;
                continue;
            }

            if (($inSingle || $inDouble) && $ch === '\\') {
                $buffer .= $ch;
                $escape = true;
                continue;
            }

            if (!$inDouble && !$inBacktick && $ch === "'") {
                $inSingle = !$inSingle;
                $buffer .= $ch;
                continue;
            }
            if (!$inSingle && !$inBacktick && $ch === '"') {
                $inDouble = !$inDouble;
                $buffer .= $ch;
                continue;
            }
            if (!$inSingle && !$inDouble && $ch === '`') {
                $inBacktick = !$inBacktick;
                $buffer .= $ch;
                continue;
            }

            if (!$inSingle && !$inDouble && !$inBacktick && $ch === ';') {
                $stmt = trim($buffer);
                if ($stmt !== '') {
                    $statements[] = $stmt;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $ch;
        }

        $stmt = trim($buffer);
        if ($stmt !== '') {
            $statements[] = $stmt;
        }

        return $statements;
    }

    /**
     * 为 SQL 中的裸表名套上前缀
     *
     * 升级/安装脚本一律书写裸表名（如 `users`），执行时统一在此套前缀，
     * 从而保证「未设前缀」与「已设前缀」两种安装环境都能正确执行。
     */
    public function applyTablePrefix(string $sql, string $prefix): string
    {
        $prefix = trim($prefix);
        if ($prefix === '') {
            return $sql;
        }

        $patterns = [
            '/(?P<keyword>CREATE\s+TABLE\s+)(?P<optional>IF\s+NOT\s+EXISTS\s+)?`?(?P<name>[a-zA-Z0-9_]+)`?/i',
            '/(?P<keyword>INSERT\s+INTO\s+)`?(?P<name>[a-zA-Z0-9_]+)`?/i',
            // DML 语句锚定到语句开头，避免误伤 DDL 里的 ON UPDATE/ON DELETE ... CASCADE
            '/(?P<keyword>^\s*UPDATE\s+)`?(?P<name>[a-zA-Z0-9_]+)`?/im',
            '/(?P<keyword>^\s*DELETE\s+FROM\s+)`?(?P<name>[a-zA-Z0-9_]+)`?/im',
            // 子查询 / JOIN
            '/(?P<keyword>\bFROM\s+)`?(?P<name>[a-zA-Z0-9_]+)`?/i',
            '/(?P<keyword>\bJOIN\s+)`?(?P<name>[a-zA-Z0-9_]+)`?/i',
            '/(?P<keyword>ALTER\s+TABLE\s+)`?(?P<name>[a-zA-Z0-9_]+)`?/i',
            '/(?P<keyword>DROP\s+TABLE\s+)(?P<optional>IF\s+EXISTS\s+)?`?(?P<name>[a-zA-Z0-9_]+)`?/i',
            '/(?P<keyword>RENAME\s+TABLE\s+)`?(?P<name>[a-zA-Z0-9_]+)`?/i',
            '/(?P<keyword>TRUNCATE\s+TABLE\s+)`?(?P<name>[a-zA-Z0-9_]+)`?/i',
            '/(?P<keyword>REFERENCES\s+)`?(?P<name>[a-zA-Z0-9_]+)`?/i',
        ];

        foreach ($patterns as $pattern) {
            $sql = preg_replace_callback($pattern, function ($matches) use ($prefix) {
                $keyword = $matches['keyword'];
                $optional = $matches['optional'] ?? '';
                $name = $matches['name'];
                if (strpos($name, $prefix) === 0) {
                    return $keyword . $optional . '`' . $name . '`';
                }
                return $keyword . $optional . '`' . $prefix . $name . '`';
            }, $sql);
        }

        return $sql;
    }
}
