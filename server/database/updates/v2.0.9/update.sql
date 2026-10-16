CREATE TABLE IF NOT EXISTS `configuration_state` (
 `id` tinyint unsigned NOT NULL,
 `revision` bigint unsigned NOT NULL DEFAULT 1,
 PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
INSERT IGNORE INTO `configuration_state` (`id`,`revision`) VALUES (1,1);
