<?php

declare(strict_types=1);

// 能力目录与允许的直接依赖。修改时同步 core/README.md。
$dependencies = [
    'apidoc' => ['auth', 'response'],
    'auth' => ['context', 'exception'],
    'base' => ['context', 'datascope', 'query', 'response', 'validation'],
    'config' => [],
    'context' => [],
    'cron' => [],
    'database' => [],
    'datascope' => ['auth', 'context'],
    'exception' => [],
    'generator' => ['exception', 'support'],
    'http' => [],
    'install' => ['database', 'exception'],
    'message' => ['config', 'sms', 'wechat'],
    'observability' => [],
    'payment' => ['config', 'exception'],
    'query' => ['exception'],
    'queue' => [],
    'realtime' => [],
    'response' => [],
    'sms' => ['config', 'exception'],
    'storage' => ['config', 'exception'],
    'support' => [],
    'validation' => ['exception'],
    'wechat' => ['config'],
];

// 可传入独立 core 目录供规则测试使用；不加载应用或基础设施。
$root = $argv[1] ?? dirname(__DIR__) . '/core';
if (!is_dir($root)) {
    fwrite(STDERR, "核心目录不存在：{$root}\n");
    exit(1);
}
$root = rtrim($root, '/');
$errors = [];
foreach (new DirectoryIterator($root) as $entry) {
    if (!$entry->isDot() && $entry->isDir() && !array_key_exists($entry->getFilename(), $dependencies)) {
        $errors[] = '未定义的核心能力目录：' . $entry->getFilename();
    }
}

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($files as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $relative = substr($file->getPathname(), strlen($root) + 1);
    if (str_starts_with($relative, 'generator/stubs/')) {
        continue;
    }
    $module = explode('/', $relative)[0];
    $allowed = $dependencies[$module] ?? [];
    if ($relative === 'support/lang.php') {
        $allowed[] = 'validation'; // Composer 引导函数的唯一例外。
    }
    $expectedNamespace = 'core\\' . str_replace('/', '\\', dirname($relative));
    $namespace = null;
    $readingNamespace = false;
    foreach (token_get_all((string) file_get_contents($file->getPathname()), TOKEN_PARSE) as $token) {
        if (!is_array($token)) {
            $readingNamespace = false;
            continue;
        }
        if ($token[0] === T_NAMESPACE) {
            $readingNamespace = true;
            continue;
        }
        if ($readingNamespace && in_array($token[0], [T_NAME_QUALIFIED, T_STRING], true)) {
            $namespace = $token[1];
            $readingNamespace = false;
            continue;
        }
        if (!in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
            continue;
        }
        $name = ltrim($token[1], '\\');
        if (str_starts_with($name, 'app\\')) {
            $errors[] = "{$relative}:{$token[2]} 核心不得依赖应用层 {$name}";
        }
        if (!str_starts_with($name, 'core\\')) {
            continue;
        }
        $target = explode('\\', $name)[1];
        if ($target !== $module && !in_array($target, $allowed, true)) {
            $errors[] = "{$relative}:{$token[2]} 禁止依赖 core\\{$target}（{$name}）";
        }
    }
    if ($namespace !== $expectedNamespace && $relative !== 'support/lang.php') {
        $errors[] = "{$relative}: 命名空间必须为 {$expectedNamespace}";
    }
}

if ($errors !== []) {
    fwrite(STDERR, implode("\n", array_unique($errors)) . "\n");
    exit(1);
}
echo '核心目录与依赖检查通过：' . count($dependencies) . " 个能力目录\n";
