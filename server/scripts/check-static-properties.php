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

/** 跳过空白/注释，返回沿 $step 方向的下一个「有意义」token（不存在则为 null）。 */
function significantToken(array $tokens, int $index, int $step): array|string|null
{
    $total = count($tokens);
    for ($index += $step; $index >= 0 && $index < $total; $index += $step) {
        $token = $tokens[$index];
        if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        return $token;
    }
    return null;
}

/**
 * tokenizer（而非正则）判断文件是否声明了名为 $name 的 class/interface/trait/enum。
 *
 * T_CLASS 既会出现在 `class Foo` 声明处，也会出现在 `Foo::class` 魔术常量里，
 * 因此不能一遇到 T_CLASS 就下结论：需要看它前一个有意义的 token 是不是
 * `::`（T_DOUBLE_COLON，说明是 ::class）或 `new`（T_NEW，说明是匿名类），
 * 是的话跳过继续扫描；只有在整段 token 流都扫完仍未命中时才返回 false。
 */
function declaresSymbol(string $source, string $name): bool
{
    $tokens = token_get_all($source);
    foreach ($tokens as $i => $token) {
        if (!is_array($token) || !in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
            continue;
        }
        $prev = significantToken($tokens, $i, -1);
        if (is_array($prev) && in_array($prev[0], [T_DOUBLE_COLON, T_NEW], true)) {
            continue; // Foo::class 魔术常量、匿名类 new class，都不是声明
        }
        $next = significantToken($tokens, $i, 1);
        if (is_array($next) && $next[0] === T_STRING && $next[1] === $name) {
            return true;
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
