<?php

declare(strict_types=1);

namespace core\database;

/** SQL 脚本切分（安装、db:reset、测试库重建共用）。 */
final class SqlScript
{
    /**
     * 按分号切分 SQL 脚本：识别单/双/反引号与反斜杠转义（字符串里的分号不切）；
     * 跳过引号外的 `-- ` 行注释（注释里的引号不会被误当成字符串开头）；丢弃空语句
     * （MySQL 对空语句报 "Query was empty"）。
     *
     * @return list<string>
     */
    public static function split(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $quote = null;
        $length = strlen($sql);
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            if ($quote !== null) {
                $buffer .= $char;
                if ($char === '\\' && $quote !== '`' && $i + 1 < $length) {
                    $buffer .= $sql[++$i];
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }
            // MySQL 的行注释要求 "--" 后跟空白
            if ($char === '-' && ($sql[$i + 1] ?? '') === '-' && ctype_space($sql[$i + 2] ?? "\n")) {
                $newline = strpos($sql, "\n", $i);
                $i = $newline === false ? $length : $newline;
                $buffer .= "\n";
                continue;
            }
            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }
            if ($char === ';') {
                self::push($statements, $buffer);
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }
        self::push($statements, $buffer);

        return $statements;
    }

    /** @param list<string> $statements */
    private static function push(array &$statements, string $buffer): void
    {
        $trimmed = trim($buffer);
        if ($trimmed !== '') {
            $statements[] = $trimmed;
        }
    }
}
