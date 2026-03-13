-- ============================================================
-- 升级脚本：system_configs 增加 config_options / config_depends 字段
-- 新增 message_templates / message_logs / wechat_auto_replies 表
-- ============================================================

-- 1. system_configs 表新增字段
ALTER TABLE `system_configs`
  ADD COLUMN `config_options` json DEFAULT NULL COMMENT '下拉选项' AFTER `config_desc`,
  ADD COLUMN `config_depends` json DEFAULT NULL COMMENT '显示依赖' AFTER `config_options`;

-- 2. 更新 storage_driver 为 select 类型
UPDATE `system_configs` SET
  `config_type` = 'select',
  `config_options` = '{"local":"本地存储","aliyun":"阿里云OSS","tencent":"腾讯云COS","qiniu":"七牛云"}',
  `config_desc` = '选择文件存储方式'
WHERE `config_key` = 'storage_driver';

-- 3. 存储配置：阿里云 OSS 依赖
UPDATE `system_configs` SET `config_depends` = '{"field":"storage_driver","value":"aliyun"}'
WHERE `config_key` IN ('storage_oss_access_key','storage_oss_access_secret','storage_oss_bucket','storage_oss_endpoint','storage_oss_domain');

-- 4. 存储配置：腾讯云 COS 依赖
UPDATE `system_configs` SET `config_depends` = '{"field":"storage_driver","value":"tencent"}'
WHERE `config_key` IN ('storage_cos_secret_id','storage_cos_secret_key','storage_cos_bucket','storage_cos_region','storage_cos_domain');

-- 5. 存储配置：七牛云依赖
UPDATE `system_configs` SET `config_depends` = '{"field":"storage_driver","value":"qiniu"}'
WHERE `config_key` IN ('storage_qiniu_access_key','storage_qiniu_secret_key','storage_qiniu_bucket','storage_qiniu_domain');

-- 6. 更新 sms_driver 为 select 类型
UPDATE `system_configs` SET
  `config_type` = 'select',
  `config_options` = '{"aliyun":"阿里云","tencent":"腾讯云"}',
  `config_desc` = '选择短信发送服务商'
WHERE `config_key` = 'sms_driver';

-- 7. 更新 smtp_encryption 为 select 类型
UPDATE `system_configs` SET
  `config_type` = 'select',
  `config_options` = '{"ssl":"SSL","tls":"TLS","none":"不加密"}',
  `config_desc` = '邮件传输加密方式'
WHERE `config_key` = 'smtp_encryption';

-- 8. 支付配置：支付宝字段依赖
UPDATE `system_configs` SET `config_depends` = '{"field":"pay_alipay_enabled","value":"1"}'
WHERE `config_key` IN ('pay_alipay_app_id','pay_alipay_private_key','pay_alipay_public_key','pay_alipay_notify_url');

-- 9. 支付配置：微信支付字段依赖
UPDATE `system_configs` SET `config_depends` = '{"field":"pay_wechat_enabled","value":"1"}'
WHERE `config_key` IN ('pay_wechat_app_id','pay_wechat_mch_id','pay_wechat_api_key','pay_wechat_api_v3_key','pay_wechat_serial_no','pay_wechat_private_key_path','pay_wechat_cert_path','pay_wechat_notify_url');

-- 10. 新增消息模板表
CREATE TABLE IF NOT EXISTS `message_templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT '模板名称',
  `code` varchar(50) NOT NULL COMMENT '模板标识',
  `sms_enabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT '启用短信',
  `sms_template_id` varchar(100) DEFAULT '' COMMENT '短信模板ID',
  `sms_content` varchar(500) DEFAULT '' COMMENT '短信内容预览',
  `wechat_official_enabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT '启用公众号模板消息',
  `wechat_official_template_id` varchar(100) DEFAULT '' COMMENT '公众号模板ID',
  `wechat_official_url` varchar(500) DEFAULT '' COMMENT '模板消息跳转URL',
  `wechat_mini_enabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT '启用小程序订阅消息',
  `wechat_mini_template_id` varchar(100) DEFAULT '' COMMENT '小程序模板ID',
  `wechat_mini_page` varchar(200) DEFAULT '' COMMENT '小程序跳转页面',
  `variables` json DEFAULT NULL COMMENT '模板变量定义',
  `remark` varchar(500) DEFAULT '' COMMENT '备注',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态:1启用,0禁用',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='消息模板表';

-- 11. 新增消息发送记录表
CREATE TABLE IF NOT EXISTS `message_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `template_id` bigint(20) unsigned DEFAULT NULL COMMENT '模板ID',
  `template_code` varchar(50) DEFAULT '' COMMENT '模板标识',
  `channel` varchar(20) NOT NULL COMMENT '发送通道:sms,wechat_official,wechat_mini',
  `receiver` varchar(200) NOT NULL COMMENT '接收者',
  `content` text COMMENT '发送内容',
  `variables` json DEFAULT NULL COMMENT '模板变量值',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0待发送,1成功,2失败',
  `error_msg` varchar(500) DEFAULT '' COMMENT '错误信息',
  `sent_at` timestamp NULL DEFAULT NULL COMMENT '发送时间',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_template_id` (`template_id`),
  KEY `idx_channel` (`channel`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='消息发送记录表';

-- 12. 拆分微信配置为独立分组 + 新增字段
-- 更新原有公众号配置的分组
UPDATE `system_configs` SET `config_group` = 'wechat_official' WHERE `config_key` IN ('wechat_official_app_id','wechat_official_app_secret','wechat_official_token','wechat_official_aes_key');
-- 更新原有小程序配置的分组
UPDATE `system_configs` SET `config_group` = 'wechat_mini' WHERE `config_key` IN ('wechat_mini_app_id','wechat_mini_app_secret');

-- 新增公众号信息字段
INSERT IGNORE INTO `system_configs` (`config_key`, `config_value`, `config_group`, `config_type`, `config_name`, `config_desc`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES
('wechat_official_name', '', 'wechat_official', 'string', '公众号名称', '微信公众号名称', 1, 1, NOW(), NOW()),
('wechat_official_original_id', '', 'wechat_official', 'string', '原始ID', '公众号原始ID，如 gh_xxxxxxxx', 2, 1, NOW(), NOW()),
('wechat_official_qrcode', '', 'wechat_official', 'file', '公众号二维码', '公众号二维码图片', 3, 1, NOW(), NOW());

-- 新增小程序信息字段
INSERT IGNORE INTO `system_configs` (`config_key`, `config_value`, `config_group`, `config_type`, `config_name`, `config_desc`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES
('wechat_mini_name', '', 'wechat_mini', 'string', '小程序名称', '微信小程序名称', 1, 1, NOW(), NOW()),
('wechat_mini_original_id', '', 'wechat_mini', 'string', '原始ID', '小程序原始ID', 2, 1, NOW(), NOW()),
('wechat_mini_qrcode', '', 'wechat_mini', 'file', '小程序二维码', '小程序二维码图片', 3, 1, NOW(), NOW()),
('wechat_mini_msg_token', '', 'wechat_mini', 'string', 'Token', '消息推送校验Token', 20, 1, NOW(), NOW()),
('wechat_mini_msg_aes_key', '', 'wechat_mini', 'string', 'EncodingAESKey', '消息推送加解密密钥', 21, 1, NOW(), NOW()),
('wechat_mini_msg_format', 'JSON', 'wechat_mini', 'select', '数据格式', '消息推送数据格式', 22, 1, NOW(), NOW());

-- 13. 新增微信自动回复表
CREATE TABLE IF NOT EXISTS `wechat_auto_replies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(20) NOT NULL COMMENT '类型:keyword,subscribe,default',
  `keyword` varchar(200) DEFAULT '' COMMENT '关键词',
  `match_type` varchar(10) DEFAULT 'exact' COMMENT '匹配方式:exact,fuzzy',
  `reply_type` varchar(10) DEFAULT 'text' COMMENT '回复类型:text,image,news',
  `content` text COMMENT '回复内容',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态:1启用,0禁用',
  `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_type` (`type`),
  KEY `idx_keyword` (`keyword`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='微信自动回复表';
