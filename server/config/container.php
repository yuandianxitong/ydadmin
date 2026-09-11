<?php

$builder = new \DI\ContainerBuilder();
$builder->useAttributes(true);
$builder->useAutowiring(true);
$builder->addDefinitions([
    // RBAC：接口与 core\auth\Permission 解析为同一个单例（清缓存与鉴权用的是同一个实例）
    core\permission\PermissionCheckerInterface::class => \DI\get(core\auth\Permission::class),
]);

return $builder->build();
