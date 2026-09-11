<?php

$builder = new \DI\ContainerBuilder();
$builder->useAttributes(true);
$builder->useAutowiring(true);
$builder->addDefinitions([
    // M0 安全默认：RBAC 落地前拒绝一切带权限点的访问；M1 改绑 core\auth\Permission
    core\permission\PermissionCheckerInterface::class => \DI\autowire(core\permission\DenyAllChecker::class),
]);

return $builder->build();
