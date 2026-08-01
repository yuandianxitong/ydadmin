<?php
/* ============================================================
 * v1.8.0：为老环境补齐默认装修页与 mobile_configs（幂等）
 * ============================================================ */
declare(strict_types=1);

return static function (\PDO $pdo, string $prefix): void {
    $pages = $prefix . 'diy_pages';
    $mobile = $prefix . 'mobile_configs';

    // 幂等补列：早期建表可能缺少 home_app_code
    $colStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $colStmt->execute([$mobile, 'home_app_code']);
    if ((int) $colStmt->fetchColumn() === 0) {
        $pdo->exec(
            "ALTER TABLE `{$mobile}` ADD COLUMN `home_app_code` varchar(80) NOT NULL DEFAULT '' COMMENT '启动首页所属应用/内置 code' AFTER `theme_colors`"
        );
    }

    $home = json_encode([
        [
            'id' => 'seed-banner',
            'type' => 'banner',
            'props' => [
                'items' => [['image' => '/static/diy/home/banner.jpg', 'link' => '']],
                'autoplay' => true,
                'interval' => 3000,
                'height' => 300,
            ],
        ],
        [
            'id' => 'seed-notice',
            'type' => 'notice',
            'props' => [
                'items' => [['text' => '欢迎使用元点Admin', 'link' => '']],
                'speed' => 3000,
                'icon' => '',
            ],
        ],
        [
            'id' => 'seed-category-nav',
            'type' => 'category-nav',
            'props' => [
                'style' => 'icon-grid',
                'rows' => 2,
                'columns' => 4,
                'items' => [
                    ['title' => '应用市场', 'icon' => '/static/diy/home/nav-app-market.png', 'link' => '/pages/discover/index'],
                    ['title' => '内容管理', 'icon' => '/static/diy/home/nav-content.png', 'link' => '/pages/discover/index'],
                    ['title' => '商城系统', 'icon' => '/static/diy/home/nav-mall.png', 'link' => '/pages/discover/index'],
                    ['title' => '同城服务', 'icon' => '/static/diy/home/nav-local.png', 'link' => '/pages/discover/index'],
                    ['title' => '会员中心', 'icon' => '/static/diy/home/nav-member.png', 'link' => '/pages/my/index'],
                    ['title' => '支付中心', 'icon' => '/static/diy/home/nav-payment.png', 'link' => '/pages/discover/index'],
                    ['title' => '数据中心', 'icon' => '/static/diy/home/nav-data.png', 'link' => '/pages/discover/index'],
                    ['title' => '全部功能', 'icon' => '/static/diy/home/nav-all.png', 'link' => '/pages/discover/index'],
                ],
            ],
        ],
        [
            'id' => 'seed-content-list',
            'type' => 'content-list',
            'props' => [
                'section_title' => '最新文章',
                'source' => 'latest',
                'category_id' => 0,
                'limit' => 6,
                'layout' => 'list',
                'show_cover' => true,
                'show_summary' => true,
                'show_date' => true,
                'more_link' => '/pages/discover/index',
            ],
        ],
    ], JSON_UNESCAPED_UNICODE);
    $member = json_encode([
        ['id' => 'seed-member-user', 'type' => 'user-info-card', 'props' => [
            'show_assets' => true,
            'assets' => [
                ['label' => '余额', 'stat_key' => 'user.balance', 'link' => '/modules/user/pages/balance'],
                ['label' => '积分', 'stat_key' => 'user.points', 'link' => '/modules/user/pages/points'],
            ],
        ]],
        ['id' => 'seed-member-menu', 'type' => 'service-menu', 'props' => ['items' => [
            ['icon' => '', 'text' => '个人资料', 'link' => '/modules/user/pages/edit-profile'],
            ['icon' => '', 'text' => '修改密码', 'link' => '/modules/user/pages/change-password'],
            ['icon' => '', 'text' => '关于我们', 'link' => '/modules/about/pages/about'],
            ['icon' => '', 'text' => '设置', 'link' => '/modules/user/pages/settings'],
        ]]],
    ], JSON_UNESCAPED_UNICODE);
    $settings = json_encode(['background_color' => ''], JSON_UNESCAPED_UNICODE);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$pages}` WHERE `page_key` = ? AND `platform` = 'uniapp' AND `deleted_at` IS NULL");
    foreach ([
        ['home', 'home', '首页', $home],
        ['member', 'member', '个人中心', $member],
    ] as [$type, $key, $title, $components]) {
        $stmt->execute([$key]);
        if ((int) $stmt->fetchColumn() === 0) {
            $ins = $pdo->prepare("INSERT INTO `{$pages}` (`page_type`,`page_key`,`platform`,`title`,`components_draft`,`components_published`,`page_settings`,`status`,`created_at`,`updated_at`) VALUES (?,?,?,?,?,?,?,1,NOW(),NOW())");
            $ins->execute([$type, $key, 'uniapp', $title, $components, $components, $settings]);
        }
    }

    $count = (int) $pdo->query("SELECT COUNT(*) FROM `{$mobile}`")->fetchColumn();
    if ($count === 0) {
        $tabbar = json_encode([
            ['code' => '__home__', 'path' => 'pages/index/index', 'text' => '首页', 'icon' => '/static/diy/tabbar/home.png', 'selected_icon' => '/static/diy/tabbar/home-active.png'],
            ['code' => '__discover__', 'path' => 'pages/discover/index', 'text' => '发现', 'icon' => '/static/diy/tabbar/discover.png', 'selected_icon' => '/static/diy/tabbar/discover-active.png'],
            ['code' => '__message__', 'path' => 'pages/message/index', 'text' => '消息', 'icon' => '/static/diy/tabbar/message.png', 'selected_icon' => '/static/diy/tabbar/message-active.png'],
            ['code' => '__my__', 'path' => 'pages/my/index', 'text' => '我的', 'icon' => '/static/diy/tabbar/my.png', 'selected_icon' => '/static/diy/tabbar/my-active.png'],
        ], JSON_UNESCAPED_UNICODE);
        $theme = json_encode([
            'primary' => '#2979ff', 'dark' => '#1e5bb8', 'price' => '#fa3534',
            'page_bg' => '#f5f5f5', 'button_text' => '#ffffff', 'badge' => '#fa3534',
        ], JSON_UNESCAPED_UNICODE);
        $style = json_encode([
            'text_color' => '#999999', 'active_color' => '#2979ff', 'bg_color' => '#ffffff',
        ], JSON_UNESCAPED_UNICODE);
        $ins = $pdo->prepare("INSERT INTO `{$mobile}` (`app_name`,`app_logo`,`theme_color`,`theme_colors`,`home_page`,`tabbar_json`,`tabbar_style`,`status`,`created_at`,`updated_at`) VALUES ('','','#2979ff',?,'',?,?,1,NOW(),NOW())");
        $ins->execute([$theme, $tabbar, $style]);
    }
};
