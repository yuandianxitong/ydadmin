<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

// 静态门禁只读 JSON 与源码路径，不加载 .env、应用引导或数据库连接。
$root = dirname(__DIR__);
$result = (new core\module\ModuleCatalog())->inspect($root, dirname($root));
foreach ($result['errors'] as $error) {
    fwrite(STDERR, $error['path'] . ': ' . $error['message'] . "\n");
}
if ($result['errors'] !== []) {
    exit(1);
}
echo '模块描述检查通过：' . count($result['modules']) . " 个模块\n";
