<?php
/* ============================================================
 * v1.8.1：mobile_configs 补齐 home_app_code 列（幂等）
 * ============================================================ */
declare(strict_types=1);

return static function (\PDO $pdo, string $prefix): void {
    $mobile = $prefix . 'mobile_configs';

    $colStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $colStmt->execute([$mobile, 'home_app_code']);
    if ((int) $colStmt->fetchColumn() === 0) {
        $pdo->exec(
            "ALTER TABLE `{$mobile}` ADD COLUMN `home_app_code` varchar(80) NOT NULL DEFAULT '' COMMENT '启动首页所属应用/内置 code' AFTER `theme_colors`"
        );
    }
};
