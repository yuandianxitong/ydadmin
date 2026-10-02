<?php

declare(strict_types=1);

/**
 * 消息模板增加邮件通道。可重跑：列已存在则跳过。
 * 新装走 schema.sql，这里只给已经装过的库补列，不改已有模板内容。
 */
return static function (\PDO $pdo): void {
    $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $exists = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $columns = [
        'email_enabled' => "ADD COLUMN `email_enabled` tinyint NOT NULL DEFAULT 0 COMMENT '邮件通道开关' AFTER `site_content`",
        'email_subject' => "ADD COLUMN `email_subject` varchar(200) NOT NULL DEFAULT '' COMMENT '邮件主题（可含变量）' AFTER `email_enabled`",
        'email_content' => "ADD COLUMN `email_content` varchar(2000) NOT NULL DEFAULT '' COMMENT '邮件正文（可含变量）' AFTER `email_subject`",
    ];
    foreach ($columns as $name => $ddl) {
        $exists->execute([$database, 'message_templates', $name]);
        if ((int) $exists->fetchColumn() === 0) {
            $pdo->exec('ALTER TABLE `message_templates` ' . $ddl);
        }
    }
};
