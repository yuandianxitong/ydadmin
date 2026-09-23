<?php

declare(strict_types=1);

use core\database\SqlScript;

/**
 * 把安装用 regions.sql 改成 INSERT IGNORE 补齐缺失的省市区。不 UPDATE 名称。
 */
return static function (\PDO $pdo): void {
    $path = dirname(__DIR__, 2) . '/install/regions.sql';
    $sql = (string) file_get_contents($path);
    $sql = preg_replace('/INSERT INTO/', 'INSERT IGNORE INTO', $sql, 1) ?? $sql;
    foreach (SqlScript::split($sql) as $statement) {
        $pdo->exec($statement);
    }
};
