<?php

use Composer\Autoload\ClassLoader;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcherContract;
use Illuminate\Events\Dispatcher;

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
    \core\plugin\contracts\PluginHostProfile::class => \DI\get(\app\service\plugin\adapter\HostProfile::class),

    // 必须是已经 register() 的那个 Composer loader：安装期 addPsr4() 挂到没人用的新实例上，
    // 钩子类就找不到，内核会把 install() 当成没有钩子而跳过。只有一个 loader 都没注册时才 new。
    ClassLoader::class => function () {
        $loaders = ClassLoader::getRegisteredLoaders();

        return $loaders !== [] ? reset($loaders) : new ClassLoader();
    },
    EventDispatcherContract::class => function () {
        return new Dispatcher();
    },
    \core\plugin\contracts\PluginStore::class => \DI\get(\app\repository\plugin\PluginRepository::class),
    \core\plugin\contracts\PluginMigrationStore::class => \DI\get(\app\repository\plugin\PluginMigrationRepository::class),
    \core\plugin\contracts\PluginSagaStore::class => \DI\get(\app\repository\plugin\PluginSagaRepository::class),
    \core\plugin\contracts\RouteGenerationStore::class => \DI\get(\app\service\plugin\adapter\PluginRouteGeneration::class),
    \core\plugin\contracts\RouteBuckets::class => \DI\get(\app\service\plugin\adapter\PluginRouteBuckets::class),
    \core\plugin\contracts\ManifestExtension::class => \DI\get(\app\service\plugin\adapter\PluginManifestAdapter::class),
    \core\plugin\contracts\PluginStateListener::class => \DI\get(\app\service\plugin\adapter\PluginStateAdapter::class),
    \core\plugin\contracts\PluginMenus::class => \DI\get(\app\service\plugin\adapter\PluginMenuAdapter::class),
    // 显式工厂：除 $registry 外的构造参数都有默认值，php-di 会把它们置空，而不是自动注入。
    \core\plugin\PluginManager::class => function (\Psr\Container\ContainerInterface $c) {
        return new \core\plugin\PluginManager(
            $c->get(\core\plugin\PluginScanner::class),
            $c->get(\core\plugin\PluginRegistry::class),
            $c->get(\core\plugin\PluginLoader::class),
            $c->get(\core\plugin\contracts\PluginStore::class),
            $c->get(\core\plugin\PluginEventRegistrar::class),
        );
    },
]);

return $builder->build();
