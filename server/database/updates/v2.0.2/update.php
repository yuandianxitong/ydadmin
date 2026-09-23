<?php

declare(strict_types=1);

/**
 * diy_pages 补一列「已发布的页面设置」。
 *
 * 原先草稿与已发布共用 page_settings：管理员只点保存不发布，C 端首页的背景色等设置就立刻变了，
 * 回滚版本同理。加列之后 C 端只读 page_settings_published，发布时才从草稿拷过去。
 * 存量行用当前 page_settings 回填——那正是 C 端此刻已经在用的值。
 */
return static function (\PDO $pdo): void {
    $exists = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'diy_pages' AND COLUMN_NAME = 'page_settings_published'"
    )->fetchColumn();

    if ((int) $exists === 0) {
        $pdo->exec("ALTER TABLE `diy_pages`
            ADD COLUMN `page_settings_published` json DEFAULT NULL COMMENT '页面设置(已发布)' AFTER `page_settings`");
        $pdo->exec('UPDATE `diy_pages` SET `page_settings_published` = `page_settings` WHERE `page_settings_published` IS NULL');
    }
};
