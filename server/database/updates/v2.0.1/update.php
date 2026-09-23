<?php

declare(strict_types=1);

use core\database\SqlScript;

/**
 * 把安装用 regions.sql 改成 INSERT IGNORE 补齐缺失的省市区。不 UPDATE 名称。
 *
 * 另外清掉 9 位码的街道行：早先的种子把东莞、中山、儋州、嘉峪关这几个不设区的市的街道
 * 也当成了第三级（编码 9 位），选到这些市时下拉里会冒出几十个街道而不是区县。
 * 本表的约定是 GB/T 2260 六位码，且没有任何表引用 regions，删掉是安全的。
 */
return static function (\PDO $pdo): void {
    $pdo->exec('DELETE FROM `regions` WHERE `id` > 999999');

    $path = dirname(__DIR__, 2) . '/install/regions.sql';
    $sql = (string) file_get_contents($path);
    $sql = preg_replace('/INSERT INTO/', 'INSERT IGNORE INTO', $sql, 1) ?? $sql;
    foreach (SqlScript::split($sql) as $statement) {
        $pdo->exec($statement);
    }
};
