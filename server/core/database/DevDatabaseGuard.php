<?php

declare(strict_types=1);

namespace core\database;

/**
 * 开发库过期检查（M1b 最终评审遗留项，M1c 落地）。
 *
 * schema.sql 只用于全新安装、不写迁移：开发者拉到新代码后如果忘了 php webman db:reset，
 * 开发库会停在上一个里程碑的结构上，然后在业务代码里撞出一个看不懂的 SQL 错误。
 * 这里在 composer test 的最前面做一次廉价断言（information_schema 查询，不连 Eloquent），
 * 缺什么就直接告诉开发者去 db:reset。
 *
 * 只登记「后加的、能一眼判定里程碑」的表与列即可，不做全量结构比对——全量比对要维护一份
 * 结构快照，成本远大于收益。后续里程碑加表时往 REQUIRED 里补一行。
 */
final class DevDatabaseGuard
{
    /** 表 => 该表必须存在的列（空数组表示只检查表本身）。 */
    private const REQUIRED = [
        'system_configs' => ['is_public'],  // M1b Task 5 加的列
        'files'          => [],            // M1c Task 2 加的表
        'failed_jobs'    => [],            // M3 Task 2 加的表
        'cron_jobs'      => [],            // M3 Task 6 加的表
        'cron_job_logs'  => [],            // M3 Task 6 加的表
        'articles'            => ['created_by', 'view_count', 'deleted_at'],
        'article_categories'  => ['parent_id'],
        'announcements'       => ['created_by', 'deleted_at'],
        'agreements'          => ['code'],
        'feedbacks'           => ['user_id', 'deleted_at'],
        'regions'             => ['parent_id', 'code', 'level'],
        'app_versions'        => ['platform', 'version_code', 'force_update'],
        'data_imports'        => ['admin_id', 'errors'],
        'dictionaries'       => [],            // M1b
        'dictionary_items'   => [],            // M1b
        'notifications'      => [],            // M1b
        'notification_reads' => [],            // M1b
        'users'              => ['mobile', 'mini_openid', 'oa_openid', 'unionid'],  // M5a + M6a 的微信列
        'balance_logs'       => ['user_id'],   // M5a
        'points_logs'        => ['user_id'],   // M5a
        'payment_orders'     => ['order_no', 'app_id'],  // M5b + M6a 的 app_id
        'refund_orders'      => ['refund_no'], // M5b
        'message_templates'  => ['code', 'site_enabled', 'wechat_official_data'],  // M6b
        'message_logs'       => ['channel', 'attempts', 'sent_at'],                // M6b
        'user_notifications' => ['user_id', 'biz_id'],                             // M6b
        'user_notification_reads' => ['notification_id'],                          // M6b
        'wechat_auto_replies'     => ['type', 'match_type'],                       // M6c
        'diy_pages'          => ['page_key', 'components_draft', 'page_settings_published', 'deleted_at'],
        'diy_page_versions'  => ['page_id', 'version_no', 'created_by'],
        'diy_links'          => ['path', 'deleted_at'],
        'mobile_configs'     => ['theme_color', 'tabbar_json'],
        'system_upgrades'    => ['version', 'applied_at'],
    ];

    /**
     * @return list<string> 缺失项描述；空数组表示结构是新的。库不存在时也返回空数组——
     *                      还没建开发库不算「过期」，新克隆的仓库不该因此跑不了测试。
     */
    public static function missing(\PDO $pdo, string $database): array
    {
        $schema = $pdo->prepare('SELECT 1 FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
        $schema->execute([$database]);
        if ($schema->fetchColumn() === false) {
            return [];
        }

        $tableStmt = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
        $columnStmt = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');

        $missing = [];
        foreach (self::REQUIRED as $table => $columns) {
            $tableStmt->execute([$database, $table]);
            if ($tableStmt->fetchColumn() === false) {
                $missing[] = "缺表 {$table}";

                continue;
            }
            foreach ($columns as $column) {
                $columnStmt->execute([$database, $table, $column]);
                if ($columnStmt->fetchColumn() === false) {
                    $missing[] = "{$table} 缺列 {$column}";
                }
            }
        }

        return $missing;
    }
}
