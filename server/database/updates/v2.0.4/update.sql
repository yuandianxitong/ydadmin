-- 补齐邮件配置，并把空的支付异步通知地址填成默认相对路径。
-- 已有邮件项（INSERT IGNORE）和管理员改过的通知地址（非空）不动。

INSERT IGNORE INTO `system_configs` (`config_key`, `config_value`, `config_group`, `config_type`, `config_name`, `config_desc`, `config_options`, `config_depends`, `sort_order`, `status`, `is_public`, `created_at`, `updated_at`) VALUES
  ('smtp_host', '', 'email', 'string', 'SMTP服务器', '例如：smtp.qq.com、smtp.163.com', NULL, NULL, 1, 1, 0, NOW(), NOW()),
  ('smtp_port', '465', 'email', 'number', 'SMTP端口', '常用端口：25(不加密)、465(SSL)、587(TLS)', NULL, NULL, 2, 1, 0, NOW(), NOW()),
  ('smtp_user', '', 'email', 'string', 'SMTP用户名', '通常为发件人邮箱地址', NULL, NULL, 3, 1, 0, NOW(), NOW()),
  ('smtp_pass', '', 'email', 'string', 'SMTP密码', 'SMTP授权码或密码', NULL, NULL, 4, 1, 0, NOW(), NOW()),
  ('smtp_from_address', '', 'email', 'string', '发件人地址', '发件人邮箱地址', NULL, NULL, 5, 1, 0, NOW(), NOW()),
  ('smtp_from_name', '元点Admin', 'email', 'string', '发件人名称', '收件人看到的发件人名称', NULL, NULL, 6, 1, 0, NOW(), NOW()),
  ('smtp_encryption', 'ssl', 'email', 'select', '加密方式', '邮件传输加密方式', '{"ssl":"SSL","tls":"TLS","none":"不加密"}', NULL, 7, 1, 0, NOW(), NOW());

UPDATE `system_configs`
SET `config_value` = '/api/payment/notify/alipay', `config_desc` = '默认相对路径，域名取网站地址。一般不用改；要换地址时填完整的 https 链接', `updated_at` = NOW()
WHERE `config_key` = 'pay_alipay_notify_url' AND (`config_value` IS NULL OR `config_value` = '');

UPDATE `system_configs`
SET `config_value` = '/api/payment/notify/wechat', `config_desc` = '默认相对路径，域名取网站地址。一般不用改；要换地址时填完整的 https 链接', `updated_at` = NOW()
WHERE `config_key` = 'pay_wechat_notify_url' AND (`config_value` IS NULL OR `config_value` = '');
