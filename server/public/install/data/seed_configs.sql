-- ============================================================
-- 系统配置种子数据
-- ============================================================

INSERT INTO `system_configs` (`config_key`, `config_value`, `config_group`, `config_type`, `config_name`, `config_desc`, `config_options`, `config_depends`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES

-- ===== 基础配置 (basic) =====
('site_name', '元点Admin', 'basic', 'string', '网站名称', '显示在浏览器标题栏和系统Logo旁', NULL, NULL, 1, 1, NOW(), NOW()),
('site_url', 'http://localhost', 'basic', 'string', '网站地址', '网站访问地址，用于生成完整链接', NULL, NULL, 2, 1, NOW(), NOW()),
('site_logo', '/storage/uploads/images/logo.png', 'basic', 'file', '网站Logo', '建议尺寸 200x50，支持 PNG/SVG 格式', NULL, NULL, 3, 1, NOW(), NOW()),
('site_favicon', '/storage/uploads/images/favicon.ico', 'basic', 'file', '网站图标', '浏览器标签页图标，建议 32x32 ICO/PNG 格式', NULL, NULL, 4, 1, NOW(), NOW()),
('site_description', '一款通用的后台管理系统', 'basic', 'string', '网站描述', '用于SEO和网站简介', NULL, NULL, 5, 1, NOW(), NOW()),
('site_keywords', '后台管理,管理系统,Admin', 'basic', 'string', 'SEO关键词', '多个关键词用英文逗号分隔', NULL, NULL, 6, 1, NOW(), NOW()),
('site_icp', '', 'basic', 'string', 'ICP备案号', '如：京ICP备XXXXXXXX号', NULL, NULL, 7, 1, NOW(), NOW()),
('site_copyright', 'Copyright © 2024 Dev007. All rights reserved.', 'basic', 'string', '版权信息', '显示在页面底部的版权声明', NULL, NULL, 8, 1, NOW(), NOW()),
('site_phone', '', 'basic', 'string', '联系电话', '网站管理员联系电话', NULL, NULL, 9, 1, NOW(), NOW()),
('site_email', '', 'basic', 'string', '联系邮箱', '网站管理员联系邮箱', NULL, NULL, 10, 1, NOW(), NOW()),
('site_address', '', 'basic', 'string', '联系地址', '公司或团队地址', NULL, NULL, 11, 1, NOW(), NOW()),
('site_status', '1', 'basic', 'boolean', '网站开关', '关闭后前台将显示维护提示', NULL, NULL, 12, 1, NOW(), NOW()),
('site_close_tip', '网站维护中，请稍后再试...', 'basic', 'string', '关闭提示', '网站关闭时显示的提示信息', NULL, NULL, 13, 1, NOW(), NOW()),
('user_register', '1', 'basic', 'boolean', '开放注册', '是否允许新用户注册', NULL, NULL, 14, 1, NOW(), NOW()),
('login_captcha', '1', 'basic', 'boolean', '登录验证码', '登录时是否需要输入验证码', NULL, NULL, 15, 1, NOW(), NOW()),
('password_min_length', '6', 'basic', 'number', '密码最小长度', '用户密码最少字符数', NULL, NULL, 16, 1, NOW(), NOW()),
('login_max_retry', '5', 'basic', 'number', '登录失败上限', '连续登录失败后锁定账号的次数', NULL, NULL, 17, 1, NOW(), NOW()),
('login_lock_duration', '30', 'basic', 'number', '锁定时长(分钟)', '账号被锁定后的等待时间', NULL, NULL, 18, 1, NOW(), NOW()),

-- ===== 邮件配置 (email) =====
('smtp_host', '', 'email', 'string', 'SMTP服务器', '例如：smtp.qq.com、smtp.163.com', NULL, NULL, 1, 1, NOW(), NOW()),
('smtp_port', '465', 'email', 'number', 'SMTP端口', '常用端口：25(不加密)、465(SSL)、587(TLS)', NULL, NULL, 2, 1, NOW(), NOW()),
('smtp_user', '', 'email', 'string', 'SMTP用户名', '通常为发件人邮箱地址', NULL, NULL, 3, 1, NOW(), NOW()),
('smtp_pass', '', 'email', 'string', 'SMTP密码', 'SMTP授权码或密码', NULL, NULL, 4, 1, NOW(), NOW()),
('smtp_from_address', '', 'email', 'string', '发件人地址', '发件人邮箱地址', NULL, NULL, 5, 1, NOW(), NOW()),
('smtp_from_name', '元点Admin', 'email', 'string', '发件人名称', '收件人看到的发件人名称', NULL, NULL, 6, 1, NOW(), NOW()),
('smtp_encryption', 'ssl', 'email', 'select', '加密方式', '邮件传输加密方式', '{"ssl":"SSL","tls":"TLS","none":"不加密"}', NULL, 7, 1, NOW(), NOW()),
('email_test_address', '', 'email', 'string', '测试收件地址', '用于发送测试邮件的收件人地址', NULL, NULL, 8, 1, NOW(), NOW()),

-- ===== 短信配置 (sms) =====
('sms_driver', 'aliyun', 'sms', 'select', '短信服务商', '选择短信发送服务商', '{"aliyun":"阿里云","tencent":"腾讯云"}', NULL, 1, 1, NOW(), NOW()),
('sms_access_key', '', 'sms', 'string', 'AccessKey ID', '短信服务商提供的 AccessKey ID', NULL, NULL, 2, 1, NOW(), NOW()),
('sms_access_secret', '', 'sms', 'string', 'AccessKey Secret', '短信服务商提供的 AccessKey Secret', NULL, NULL, 3, 1, NOW(), NOW()),
('sms_sign_name', '', 'sms', 'string', '短信签名', '已审核通过的短信签名', NULL, NULL, 4, 1, NOW(), NOW()),
('sms_template_code', '', 'sms', 'string', '验证码模板', '短信验证码模板编号', NULL, NULL, 5, 1, NOW(), NOW()),
('sms_template_notify', '', 'sms', 'string', '通知模板', '短信通知模板编号', NULL, NULL, 6, 1, NOW(), NOW()),

-- ===== 支付配置 (payment) =====
('pay_alipay_enabled', '0', 'payment', 'boolean', '启用支付宝', '是否开启支付宝支付', NULL, NULL, 1, 1, NOW(), NOW()),
('pay_alipay_app_id', '', 'payment', 'string', '支付宝AppID', '支付宝开放平台应用AppID', NULL, '{"field":"pay_alipay_enabled","value":"1"}', 2, 1, NOW(), NOW()),
('pay_alipay_private_key', '', 'payment', 'string', '应用私钥', '支付宝应用私钥(RSA2)', NULL, '{"field":"pay_alipay_enabled","value":"1"}', 3, 1, NOW(), NOW()),
('pay_alipay_public_key', '', 'payment', 'string', '支付宝公钥', '支付宝公钥', NULL, '{"field":"pay_alipay_enabled","value":"1"}', 4, 1, NOW(), NOW()),
('pay_alipay_notify_url', '', 'payment', 'string', '异步通知地址', '支付宝异步回调通知URL', NULL, '{"field":"pay_alipay_enabled","value":"1"}', 5, 1, NOW(), NOW()),
('pay_wechat_enabled', '0', 'payment', 'boolean', '启用微信支付', '是否开启微信支付', NULL, NULL, 6, 1, NOW(), NOW()),
('pay_wechat_app_id', '', 'payment', 'string', '微信AppID', '微信公众号或小程序AppID', NULL, '{"field":"pay_wechat_enabled","value":"1"}', 7, 1, NOW(), NOW()),
('pay_wechat_mch_id', '', 'payment', 'string', '微信商户号', '微信支付商户号', NULL, '{"field":"pay_wechat_enabled","value":"1"}', 8, 1, NOW(), NOW()),
('pay_wechat_api_key', '', 'payment', 'string', '微信API密钥', '微信支付APIv3密钥', NULL, '{"field":"pay_wechat_enabled","value":"1"}', 9, 1, NOW(), NOW()),
('pay_wechat_api_v3_key', '', 'payment', 'string', '微信APIv3密钥', '微信支付APIv3密钥（用于V3接口）', NULL, '{"field":"pay_wechat_enabled","value":"1"}', 10, 1, NOW(), NOW()),
('pay_wechat_serial_no', '', 'payment', 'string', '微信证书序列号', '微信支付平台证书序列号', NULL, '{"field":"pay_wechat_enabled","value":"1"}', 11, 1, NOW(), NOW()),
('pay_wechat_private_key_path', '', 'payment', 'string', '微信私钥文件', '商户API私钥文件路径（apiclient_key.pem）', NULL, '{"field":"pay_wechat_enabled","value":"1"}', 12, 1, NOW(), NOW()),
('pay_wechat_cert_path', '', 'payment', 'string', '微信证书文件', '商户API证书文件路径（apiclient_cert.pem）', NULL, '{"field":"pay_wechat_enabled","value":"1"}', 13, 1, NOW(), NOW()),
('pay_wechat_notify_url', '', 'payment', 'string', '异步通知地址', '微信支付异步回调通知URL', NULL, '{"field":"pay_wechat_enabled","value":"1"}', 14, 1, NOW(), NOW()),

-- ===== 存储配置 (storage) =====
('storage_driver', 'local', 'storage', 'select', '存储方式', '选择文件存储方式', '{"local":"本地存储","aliyun":"阿里云OSS","tencent":"腾讯云COS","qiniu":"七牛云"}', NULL, 1, 1, NOW(), NOW()),
('storage_upload_max_size', '10', 'storage', 'number', '最大上传(MB)', '单个文件最大上传大小，单位MB', NULL, NULL, 2, 1, NOW(), NOW()),
('storage_upload_allowed_ext', 'jpg,jpeg,png,gif,svg,webp,bmp,doc,docx,xls,xlsx,ppt,pptx,pdf,zip,rar,txt,csv', 'storage', 'string', '允许的文件类型', '允许上传的文件扩展名，英文逗号分隔', NULL, NULL, 3, 1, NOW(), NOW()),
('storage_image_max_size', '5', 'storage', 'number', '图片最大(MB)', '单张图片最大上传大小，单位MB', NULL, NULL, 4, 1, NOW(), NOW()),
-- 阿里云 OSS
('storage_oss_access_key', '', 'storage', 'string', 'OSS AccessKey', '阿里云OSS AccessKey ID', NULL, '{"field":"storage_driver","value":"aliyun"}', 10, 1, NOW(), NOW()),
('storage_oss_access_secret', '', 'storage', 'string', 'OSS AccessSecret', '阿里云OSS AccessKey Secret', NULL, '{"field":"storage_driver","value":"aliyun"}', 11, 1, NOW(), NOW()),
('storage_oss_bucket', '', 'storage', 'string', 'OSS Bucket', '阿里云OSS Bucket名称', NULL, '{"field":"storage_driver","value":"aliyun"}', 12, 1, NOW(), NOW()),
('storage_oss_endpoint', '', 'storage', 'string', 'OSS Endpoint', '阿里云OSS 访问域名，如 oss-cn-hangzhou.aliyuncs.com', NULL, '{"field":"storage_driver","value":"aliyun"}', 13, 1, NOW(), NOW()),
('storage_oss_domain', '', 'storage', 'string', 'OSS 自定义域名', '绑定的自定义域名，用于生成访问URL', NULL, '{"field":"storage_driver","value":"aliyun"}', 14, 1, NOW(), NOW()),
-- 腾讯云 COS
('storage_cos_secret_id', '', 'storage', 'string', 'COS SecretId', '腾讯云COS SecretId', NULL, '{"field":"storage_driver","value":"tencent"}', 20, 1, NOW(), NOW()),
('storage_cos_secret_key', '', 'storage', 'string', 'COS SecretKey', '腾讯云COS SecretKey', NULL, '{"field":"storage_driver","value":"tencent"}', 21, 1, NOW(), NOW()),
('storage_cos_bucket', '', 'storage', 'string', 'COS Bucket', '腾讯云COS Bucket名称（含AppId后缀，如 bucket-1250000000）', NULL, '{"field":"storage_driver","value":"tencent"}', 22, 1, NOW(), NOW()),
('storage_cos_region', '', 'storage', 'string', 'COS Region', '腾讯云COS 地域，如 ap-guangzhou', NULL, '{"field":"storage_driver","value":"tencent"}', 23, 1, NOW(), NOW()),
('storage_cos_domain', '', 'storage', 'string', 'COS 自定义域名', '绑定的自定义域名，用于生成访问URL', NULL, '{"field":"storage_driver","value":"tencent"}', 24, 1, NOW(), NOW()),
-- 七牛云
('storage_qiniu_access_key', '', 'storage', 'string', '七牛 AccessKey', '七牛云 AccessKey', NULL, '{"field":"storage_driver","value":"qiniu"}', 30, 1, NOW(), NOW()),
('storage_qiniu_secret_key', '', 'storage', 'string', '七牛 SecretKey', '七牛云 SecretKey', NULL, '{"field":"storage_driver","value":"qiniu"}', 31, 1, NOW(), NOW()),
('storage_qiniu_bucket', '', 'storage', 'string', '七牛 Bucket', '七牛云存储空间名称', NULL, '{"field":"storage_driver","value":"qiniu"}', 32, 1, NOW(), NOW()),
('storage_qiniu_domain', '', 'storage', 'string', '七牛访问域名', '七牛云存储空间绑定的域名（含协议，如 https://cdn.example.com）', NULL, '{"field":"storage_driver","value":"qiniu"}', 33, 1, NOW(), NOW()),

-- ===== 公众号配置 (wechat_official) =====
-- 公众号信息
('wechat_official_name', '', 'wechat_official', 'string', '公众号名称', '微信公众号名称', NULL, NULL, 1, 1, NOW(), NOW()),
('wechat_official_original_id', '', 'wechat_official', 'string', '原始ID', '公众号原始ID，如 gh_xxxxxxxx', NULL, NULL, 2, 1, NOW(), NOW()),
('wechat_official_qrcode', '', 'wechat_official', 'file', '公众号二维码', '公众号二维码图片，建议 200x200', NULL, NULL, 3, 1, NOW(), NOW()),
-- 开发信息
('wechat_official_app_id', '', 'wechat_official', 'string', 'AppID', '微信公众号AppID（开发者ID）', NULL, NULL, 10, 1, NOW(), NOW()),
('wechat_official_app_secret', '', 'wechat_official', 'string', 'AppSecret', '微信公众号AppSecret（开发者密码）', NULL, NULL, 11, 1, NOW(), NOW()),
-- 服务器配置
('wechat_official_token', '', 'wechat_official', 'string', 'Token', '微信公众号消息校验Token', NULL, NULL, 20, 1, NOW(), NOW()),
('wechat_official_aes_key', '', 'wechat_official', 'string', 'EncodingAESKey', '微信公众号消息加解密密钥（43位字符）', NULL, NULL, 21, 1, NOW(), NOW()),

-- ===== 小程序配置 (wechat_mini) =====
-- 小程序信息
('wechat_mini_name', '', 'wechat_mini', 'string', '小程序名称', '微信小程序名称', NULL, NULL, 1, 1, NOW(), NOW()),
('wechat_mini_original_id', '', 'wechat_mini', 'string', '原始ID', '小程序原始ID，如 gh_xxxxxxxx', NULL, NULL, 2, 1, NOW(), NOW()),
('wechat_mini_qrcode', '', 'wechat_mini', 'file', '小程序二维码', '小程序二维码图片，建议 200x200', NULL, NULL, 3, 1, NOW(), NOW()),
-- 开发信息
('wechat_mini_app_id', '', 'wechat_mini', 'string', 'AppID', '微信小程序AppID', NULL, NULL, 10, 1, NOW(), NOW()),
('wechat_mini_app_secret', '', 'wechat_mini', 'string', 'AppSecret', '微信小程序AppSecret', NULL, NULL, 11, 1, NOW(), NOW()),
-- 消息推送配置
('wechat_mini_msg_token', '', 'wechat_mini', 'string', 'Token', '消息推送校验Token', NULL, NULL, 20, 1, NOW(), NOW()),
('wechat_mini_msg_aes_key', '', 'wechat_mini', 'string', 'EncodingAESKey', '消息推送加解密密钥（43位字符）', NULL, NULL, 21, 1, NOW(), NOW()),
('wechat_mini_msg_format', 'JSON', 'wechat_mini', 'select', '数据格式', '消息推送数据格式', '{"JSON":"JSON","XML":"XML"}', NULL, 22, 1, NOW(), NOW());
