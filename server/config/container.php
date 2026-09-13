<?php

$builder = new \DI\ContainerBuilder();
$builder->useAttributes(true);
$builder->useAutowiring(true);
$builder->addDefinitions([
    // RBAC：接口与 core\auth\Permission 解析为同一个单例（清缓存与鉴权用的是同一个实例）
    core\permission\PermissionCheckerInterface::class => \DI\get(core\auth\Permission::class),
    // 存储：core\storage\StorageManager 经这个接口读系统配置，core/ 不 use app\（check:context 规则六）
    core\contract\ConfigValueReader::class => \DI\get(app\repository\system\SystemConfigRepository::class),
]);

return $builder->build();
