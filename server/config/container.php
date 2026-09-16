<?php

$builder = new \DI\ContainerBuilder();
$builder->useAttributes(true);
$builder->useAutowiring(true);
$builder->addDefinitions([
    // RBAC：接口与 core\auth\Permission 解析为同一个单例（清缓存与鉴权用的是同一个实例）
    core\permission\PermissionCheckerInterface::class => \DI\get(core\auth\Permission::class),
    // 存储：core\storage\StorageManager 经这个接口读系统配置，core/ 不 use app\（check:context 规则六）
    core\contract\ConfigValueReader::class => \DI\get(app\repository\system\SystemConfigRepository::class),
    // 短信：注入点要的是「当前配置选出来的那个驱动」，所以绑成工厂而不是 \DI\get——
    // \DI\get 只能指向一个具体类，而选哪个驱动要问 SmsManager。SmsManager 保持 final：
    // 需要假驱动的测试直接实现 SmsInterface，不继承它，也不改这里的绑定。
    // ⚠️ php-di 的定义默认共享：这条在一个 worker 进程里只解析一次，管理员换了短信服务商或凭据后
    // 需要 php start.php reload 才生效（SmsManager 自身仍是每次现读现 new，没有缓存驱动）。
    core\sms\SmsInterface::class => \DI\factory(
        static fn (core\sms\SmsManager $manager): core\sms\SmsInterface => $manager->driver()
    ),
]);

return $builder->build();
