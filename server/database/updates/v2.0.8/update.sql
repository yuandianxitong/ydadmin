CREATE TABLE IF NOT EXISTS `queue_outbox` (
 `id` bigint unsigned NOT NULL AUTO_INCREMENT,
 `queue` varchar(64) NOT NULL,
 `dedup_key` char(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
 `payload` json NOT NULL,
 `trace_id` varchar(128) NOT NULL DEFAULT '',
 `locale` varchar(16) NOT NULL DEFAULT 'zh_CN',
 `status` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '0 pending 1 published 2 running 3 completed 4 failed',
 `attempts` int unsigned NOT NULL DEFAULT 0,
 `available_at` bigint unsigned NOT NULL,
 `lease_until` bigint unsigned NOT NULL DEFAULT 0,
 `lease_token` char(32) NOT NULL DEFAULT '',
 `last_error` varchar(255) NOT NULL DEFAULT '',
 `created_at` bigint unsigned NOT NULL,
 `updated_at` bigint unsigned NOT NULL,
 PRIMARY KEY (`id`), UNIQUE KEY `uk_dedup` (`dedup_key`),
 KEY `idx_dispatch` (`status`,`available_at`,`lease_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
CREATE TABLE IF NOT EXISTS `http_idempotency` (
 `id` bigint unsigned NOT NULL AUTO_INCREMENT,
 `actor_id` bigint unsigned NOT NULL,
 `operation` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 `key_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 `fingerprint` char(64) NOT NULL,
 `response_body` mediumtext DEFAULT NULL,
 `http_status` smallint unsigned NOT NULL DEFAULT 200,
 `expires_at` bigint unsigned NOT NULL,
 PRIMARY KEY (`id`), UNIQUE KEY `uk_request` (`actor_id`,`operation`,`key_hash`), KEY `idx_expiry` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
CREATE TABLE IF NOT EXISTS `background_tasks` (
 `id` bigint unsigned NOT NULL AUTO_INCREMENT,
 `public_id` char(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 `admin_id` bigint unsigned NOT NULL,
 `kind` varchar(16) NOT NULL,
 `module` varchar(32) NOT NULL,
 `permission` varchar(100) NOT NULL,
 `input` json NOT NULL,
 `status` varchar(16) NOT NULL DEFAULT 'queued',
 `cursor` bigint unsigned NOT NULL DEFAULT 0,
 `processed` int unsigned NOT NULL DEFAULT 0,
 `success_count` int unsigned NOT NULL DEFAULT 0,
 `fail_count` int unsigned NOT NULL DEFAULT 0,
 `errors` json NOT NULL,
 `result` json NOT NULL,
 `worker_token` char(32) NOT NULL DEFAULT '',
 `created_at` bigint unsigned NOT NULL,
 `updated_at` bigint unsigned NOT NULL,
 PRIMARY KEY (`id`), UNIQUE KEY `uk_public_id` (`public_id`), KEY `idx_owner` (`admin_id`,`id`), KEY `idx_status` (`status`,`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
