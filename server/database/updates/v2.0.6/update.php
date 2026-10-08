<?php

declare(strict_types=1);

/**
 * 管理员和会员增加 token_version。可重跑：列已存在则跳过。
 * 新装走 schema.sql。版本号写在账号行上，和禁用、改密处在同一个事务里。
 */
return static function (\PDO $pdo): void {
    $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $exists = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $columns = [
        'admins' => "ADD COLUMN `token_version` int unsigned NOT NULL DEFAULT 0 COMMENT '令牌版本，禁用/改密时自增' AFTER `status`",
        'users'  => "ADD COLUMN `token_version` int unsigned NOT NULL DEFAULT 0 COMMENT '令牌版本，禁用/改密时自增' AFTER `status`",
    ];
    foreach ($columns as $table => $ddl) {
        $exists->execute([$database, $table, 'token_version']);
        if ((int) $exists->fetchColumn() === 0) {
            $pdo->exec('ALTER TABLE `' . $table . '` ' . $ddl);
        }
    }
};
