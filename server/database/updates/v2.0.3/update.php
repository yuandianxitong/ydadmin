<?php

declare(strict_types=1);

/**
 * 修种子里的死链图片路径。
 *
 * tabBar 图标原先种成 /static/diy/tabbar/*.png，uniapp 里实际是 /static/tabbar/*.png，
 * 新装装完就是四个碎图。首页示例里的 /static/diy/home/*.png 仓库里根本没有，置空。
 *
 * 只做字符串替换，管理员自己换过的路径不受影响（那些不含这两个前缀）。
 */
return static function (\PDO $pdo): void {
    $pdo->exec("UPDATE `mobile_configs`
        SET `tabbar_json` = REPLACE(`tabbar_json`, '/static/diy/tabbar/', '/static/tabbar/')
        WHERE `tabbar_json` LIKE '%/static/diy/tabbar/%'");

    // 同平台同 version_code 两行时「最新启用版本」取哪条不确定；改成唯一索引。
    // 已有重复数据时 ALTER 会失败，那种库要先自己清掉重复行（升级会停在这一版，不会半途污染）。
    $hasUnique = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'app_versions' AND INDEX_NAME = 'uk_platform_version_code'"
    )->fetchColumn();
    if ((int) $hasUnique === 0) {
        $pdo->exec('ALTER TABLE `app_versions` DROP INDEX `idx_platform_version`, ADD UNIQUE KEY `uk_platform_version_code` (`platform`, `version_code`)');
    }

    foreach (['components_draft', 'components_published'] as $column) {
        $pdo->exec("UPDATE `diy_pages`
            SET `{$column}` = REGEXP_REPLACE(`{$column}`, '/static/diy/home/[a-z-]+\\\\.(png|jpg)', '')
            WHERE `{$column}` LIKE '%/static/diy/home/%'");
    }
};
