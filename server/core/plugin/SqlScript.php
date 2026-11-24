<?php

declare(strict_types=1);

namespace core\plugin;

use core\exception\BusinessException;

/**
 * 插件 SQL 脚本分句器：把 `.sql` 文件内容切成可逐句 `support\Db::statement()` 的
 * 语句数组。
 *
 * - 剥离 `-- ` / `#` 行注释与 C 风格块注释
 * - 正确处理单/双引号字符串字面量内的分号与转义引号（`\'` 与 `''`）
 * - 不支持存储过程：遇到 DELIMITER 指令直接报错（422）
 */
final class SqlScript
{
    /** @return string[] */
    public static function split(string $sql): array
    {
        if (preg_match('/^\s*DELIMITER\b/im', $sql)) {
            throw new BusinessException('插件 SQL 不支持 DELIMITER/存储过程', 422);
        }

        $stmts = [];
        $buf = '';
        $len = strlen($sql);
        $inString = null; // null | "'" | '"'
        $i = 0;

        while ($i < $len) {
            $ch = $sql[$i];

            if ($inString !== null) {
                $buf .= $ch;
                if ($ch === '\\' && $i + 1 < $len) {
                    $buf .= $sql[$i + 1];
                    $i += 2;
                    continue;
                }
                if ($ch === $inString) {
                    // '' / "" 双写转义
                    if ($i + 1 < $len && $sql[$i + 1] === $inString) {
                        $buf .= $sql[$i + 1];
                        $i += 2;
                        continue;
                    }
                    $inString = null;
                }
                $i++;
                continue;
            }

            // 行注释
            if ($ch === '#' || ($ch === '-' && substr($sql, $i, 3) === '-- ')) {
                $nl = strpos($sql, "\n", $i);
                $i = $nl === false ? $len : $nl + 1;
                continue;
            }
            // 块注释
            if ($ch === '/' && substr($sql, $i, 2) === '/*') {
                $end = strpos($sql, '*/', $i + 2);
                $i = $end === false ? $len : $end + 2;
                continue;
            }

            if ($ch === "'" || $ch === '"') {
                $inString = $ch;
                $buf .= $ch;
                $i++;
                continue;
            }

            if ($ch === ';') {
                $trimmed = trim($buf);
                if ($trimmed !== '') {
                    $stmts[] = $trimmed;
                }
                $buf = '';
                $i++;
                continue;
            }

            $buf .= $ch;
            $i++;
        }

        $trimmed = trim($buf);
        if ($trimmed !== '') {
            $stmts[] = $trimmed;
        }
        return $stmts;
    }
}
