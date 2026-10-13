CREATE TABLE IF NOT EXISTS `authorization_state` (
  `id` tinyint unsigned NOT NULL,
  `version` bigint unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='权限和数据范围持久化代次';

INSERT IGNORE INTO `authorization_state` (`id`, `version`) VALUES (1, 1);

CREATE TABLE IF NOT EXISTS `auth_revocations` (
  `scope` varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `kind` varchar(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `identifier` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `expires_at` bigint unsigned NOT NULL,
  PRIMARY KEY (`scope`, `kind`, `identifier`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='令牌和登录会话吊销记录';
