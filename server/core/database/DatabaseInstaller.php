<?php

declare(strict_types=1);

namespace core\database;

/**
 * 删库重建并导入安装脚本。只用于开发库（db:reset）与测试库（tests/bootstrap.php）；
 * M8 安装向导复用 connect() 与 SqlScript，但不会 DROP 已有库。
 */
final class DatabaseInstaller
{
    /** @param array<string, mixed> $connection config('database.connections.mysql') 形状 */
    public static function connect(array $connection): \PDO
    {
        return new \PDO(
            sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $connection['host'] ?? '127.0.0.1', $connection['port'] ?? 3306),
            (string) ($connection['username'] ?? 'root'),
            (string) ($connection['password'] ?? ''),
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
    }

    public static function reinstall(\PDO $pdo, string $database, string $installDir): void
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $database) !== 1) {
            throw new \InvalidArgumentException("非法的数据库名：{$database}");
        }
        $pdo->exec("DROP DATABASE IF EXISTS `{$database}`");
        $pdo->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
        $pdo->exec("USE `{$database}`");
        foreach (['schema.sql', 'init.sql'] as $file) {
            foreach (SqlScript::split((string) file_get_contents($installDir . '/' . $file)) as $statement) {
                $pdo->exec($statement);
            }
        }
    }

    /** schema.sql 或 init.sql 任一变化，指纹就变（测试库据此自动重建）。 */
    public static function fingerprint(string $installDir): string
    {
        return md5((string) file_get_contents($installDir . '/schema.sql') . "\0" . (string) file_get_contents($installDir . '/init.sql'));
    }
}
