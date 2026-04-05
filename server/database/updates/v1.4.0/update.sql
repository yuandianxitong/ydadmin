-- v1.4.0 数据库升级脚本
-- 升级前请备份数据库

-- 1. 新增队列失败任务表
CREATE TABLE IF NOT EXISTS `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `connection` varchar(255) NOT NULL COMMENT '连接名',
  `queue` varchar(255) NOT NULL COMMENT '队列名',
  `payload` longtext NOT NULL COMMENT '任务数据',
  `exception` longtext NOT NULL COMMENT '异常信息',
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '失败时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='队列失败任务表';

-- 2. admin_login_logs 新增 (admin_id, login_time) 复合索引
ALTER TABLE `admin_login_logs`
  ADD INDEX `idx_admin_id_login_time` (`admin_id`, `login_time`);
