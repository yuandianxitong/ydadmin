<?php

$builder = new \DI\ContainerBuilder();
$builder->useAttributes(true);
$builder->useAutowiring(true);
$builder->addDefinitions([
    // 接口 → 实现的显式绑定放这里（Task 7 起追加）
]);

return $builder->build();
