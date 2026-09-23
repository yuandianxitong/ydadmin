-- 会员导入按钮 + 充值订单页（现网 INSERT IGNORE，新装走 init.sql）
INSERT IGNORE INTO `menus` (`id`, `parent_id`, `type`, `title`, `name`, `path`, `component`, `redirect`, `icon`, `permission`, `is_hidden`, `is_cache`, `is_affix`, `is_iframe`, `external_link`, `breadcrumb`, `active_menu`, `meta`, `status`, `sort`, `created_at`, `updated_at`) VALUES
  (905, 900, 3, '导入用户', NULL, NULL, NULL, NULL, NULL, 'user.import', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 5, NOW(), NOW()),
  (930, 9, 2, '充值订单', 'UserPaymentOrder', '/user/payment-order', '/user/payment-order/index', NULL, 'i-svg:wallet', 'payment.order.list', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 4, NOW(), NOW()),
  (931, 930, 3, '查看详情', NULL, NULL, NULL, NULL, NULL, 'payment.order.detail', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (932, 930, 3, '发起退款', NULL, NULL, NULL, NULL, NULL, 'payment.order.refund', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW());
