-- v1.6.0 升级脚本
-- 修正 failed_jobs 表排序规则漂移：其余表已在 v1.5.3 统一为 utf8mb4_0900_ai_ci，
-- 但 failed_jobs（v1.4.0 新增）当时仍为 utf8mb4_unicode_ci，此处补齐。
-- 前置条件：MySQL 8.0+
-- 表名写裸名即可，php think yd:update 会自动套用表前缀。

ALTER TABLE `failed_jobs` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
