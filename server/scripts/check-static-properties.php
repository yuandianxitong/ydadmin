<?php

declare(strict_types=1);

/**
 * 常驻内存纪律检查·规则一（spec §3.4）：反射版可变静态属性扫描。
 *
 * 为什么用反射而不是正则匹配源码：正则依赖修饰符书写顺序（private static /
 * static private）、是否换行、是否逗号并列声明多个属性等具体写法，任何合法但换
 * 了写法的 PHP 语法都可能绕过正则漏检；反射直接读取引擎已解析好的属性元数据，
 * 与源码书写方式无关，不存在这一类绕过方式。
 *
 * 用法：php scripts/check-static-properties.php "文件:$属性" ...（白名单，调用方传入）
 */

require_once __DIR__ . '/../vendor/autoload.php';

/** tokenizer（而非正则）判断文件是否声明了名为 $name 的 class/interface/trait/enum。 */
function declaresSymbol(string $source, string $name): bool
{
    $tokens = token_get_all($source);
    foreach ($tokens as $i => $token) {
        if (!is_array($token) || !in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
            continue;
        }
        for ($j = $i + 1; $j < count($tokens); $j++) {
            $next = $tokens[$j];
            if (is_array($next) && $next[0] === T_WHITESPACE) {
                continue;
            }
            return is_array($next) && $next[0] === T_STRING && $next[1] === $name;
        }
    }
    return false;
}

$root = dirname(__DIR__);
$whitelist = array_slice($argv, 1);
$violations = [];

foreach (['app', 'core'] as $dir) {
    if (!is_dir("{$root}/{$dir}")) {
        continue;
    }
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/{$dir}", FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        /** @var SplFileInfo $file */
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $relative = substr($file->getPathname(), strlen($root) + 1);
        $fqcn = str_replace('/', '\\', substr($relative, 0, -4));
        $short = (string) substr($fqcn, (int) strrpos($fqcn, '\\') + 1);

        // app/functions.php、core/support/lang.php 之类只定义函数，没有对应的类，跳过。
        if (!declaresSymbol((string) file_get_contents($file->getPathname()), $short)) {
            continue;
        }
        if (!class_exists($fqcn) && !interface_exists($fqcn) && !trait_exists($fqcn) && !enum_exists($fqcn)) {
            continue;
        }

        $class = new ReflectionClass($fqcn);
        foreach ($class->getProperties(ReflectionProperty::IS_STATIC) as $property) {
            if ($property->getDeclaringClass()->getName() !== $fqcn) {
                continue; // 继承来的静态属性在其声明处已被检查过
            }
            $key = $relative . ':$' . $property->getName();
            if (!in_array($key, $whitelist, true)) {
                $violations[] = $key;
            }
        }
    }
}

if ($violations !== []) {
    foreach ($violations as $violation) {
        echo "❌ 可变静态属性（违反常驻内存纪律，请求态请放 support\\Context）：{$violation}\n";
    }
    exit(1);
}

echo "✅ 规则一通过：无未登记的可变静态属性\n";
exit(0);
