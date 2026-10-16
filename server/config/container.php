<?php

$builder = new \DI\ContainerBuilder();
$builder->useAttributes(true);
$builder->useAutowiring(true);
$builder->addDefinitions([
    core\queue\ReliableQueue::class => \DI\get(app\service\operations\OutboxService::class),
    // RBAC：接口与 core\auth\PermissionChecker 解析为同一个单例（清缓存与鉴权用的是同一个实例）
    core\auth\PermissionCheckerInterface::class => \DI\get(core\auth\PermissionChecker::class),
    // 存储：core\storage\StorageManager 经这个接口读系统配置，core/ 不 use app\（check:context 规则六）
    core\config\ConfigValueReader::class => \DI\get(app\repository\system\SystemConfigRepository::class),
    // 安装：core/install 经接口建超管，core/ 不 use app\（check:context 规则六）
    core\install\SuperAdminInitializer::class => \DI\get(app\service\system\AdminService::class),
    core\install\Installer::class => \DI\factory(static function (core\install\SuperAdminInitializer $admins): core\install\Installer {
        return new core\install\Installer(
            $admins,
            base_path() . '/database/install',
            base_path() . '/.env',
            base_path() . '/.env.example',
            (string) config('install.lock', base_path('config/install.lock')),
        );
    }),

    // 共享的是无状态管理器；每次 send() 按当前配置构造驱动，凭据变更无需重启。
    core\sms\SmsInterface::class => \DI\get(core\sms\SmsManager::class),
    // 支付：服务注入的是接口，测试用 Container::set 换成假解析器。用 \DI\get 而不是工厂：
    // PaymentManager 每次 gateway() 都现读配置、现 new 驱动，所以改支付配置不需要 reload。
    core\payment\GatewayResolver::class => \DI\get(core\payment\PaymentManager::class),
    // 邮件：SmtpMailer 每次 send() 现读 smtp_*，改配置不需要 reload。
    core\message\mail\MailerInterface::class => \DI\get(core\message\mail\SmtpMailer::class),
]);

return $builder->build();
