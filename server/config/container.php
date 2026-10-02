<?php

$builder = new \DI\ContainerBuilder();
$builder->useAttributes(true);
$builder->useAutowiring(true);
$builder->addDefinitions([
    // RBAC：接口与 core\auth\Permission 解析为同一个单例（清缓存与鉴权用的是同一个实例）
    core\permission\PermissionCheckerInterface::class => \DI\get(core\auth\Permission::class),
    // 存储：core\storage\StorageManager 经这个接口读系统配置，core/ 不 use app\（check:context 规则六）
    core\contract\ConfigValueReader::class => \DI\get(app\repository\system\SystemConfigRepository::class),
    // 安装：core/install 经接口建超管，core/ 不 use app\（check:context 规则六）
    core\contract\SuperAdminInitializer::class => \DI\get(app\service\system\AdminService::class),
    core\install\Installer::class => \DI\factory(static function (core\contract\SuperAdminInitializer $admins): core\install\Installer {
        return new core\install\Installer(
            $admins,
            base_path() . '/database/install',
            base_path() . '/.env',
            base_path() . '/.env.example',
            (string) config('install.lock', base_path('config/install.lock')),
        );
    }),

    // 短信：注入点要的是「当前配置选出来的那个驱动」，所以绑成工厂而不是 \DI\get——
    // \DI\get 只能指向一个具体类，而选哪个驱动要问 SmsManager。SmsManager 保持 final：
    // 需要假驱动的测试直接实现 SmsInterface，不继承它，也不改这里的绑定。
    // ⚠️ php-di 的定义默认共享：这条在一个 worker 进程里只解析一次，管理员换了短信服务商或凭据后
    // 需要 php start.php reload 才生效（SmsManager 自身仍是每次现读现 new，没有缓存驱动）。
    core\sms\SmsInterface::class => \DI\factory(
        static fn (core\sms\SmsManager $manager): core\sms\SmsInterface => $manager->driver()
    ),
    // 支付：服务注入的是接口，测试用 Container::set 换成假解析器。用 \DI\get 而不是工厂：
    // PaymentManager 每次 gateway() 都现读配置、现 new 驱动，所以改支付配置不需要 reload（与上面的短信不同）。
    core\payment\GatewayResolver::class => \DI\get(core\payment\PaymentManager::class),
    // 邮件：SmtpMailer 每次 send() 现读 smtp_*，改配置不需要 reload。
    core\mail\MailerInterface::class => \DI\get(core\mail\SmtpMailer::class),
]);

return $builder->build();
