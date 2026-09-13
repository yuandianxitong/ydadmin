<?php

// core/generator/stubs 下的 *.stub.php 是代码生成器的模板文件，不是真正的 PHP 文件
// （里面天然会出现 use app\...、未闭合的类片段等写法），cs-fixer 不该格式化它们。
// exclude() 的参数相对 in() 的每个目录解析，只有 core/generator/stubs 存在，
// 不会误伤 app/、tests/、scripts/ 下同名目录（用 notName('*.stub.php') 会作用于
// 整个 Finder，排除粒度过宽）。
//
// tests/fixtures 下是代码生成器的黄金期望文件（`.php`，逐字节比对用）：它们必须原样冻结，
// 唯一的更新路径是 YDADMIN_UPDATE_GOLDEN=1 重新生成再审 diff。若不排除，任何一次全仓库
// `composer lint:fix` 都会把它们改写成 cs-fixer 认为的 PSR-12 版本，绕开这条唯一路径——
// 之后黄金测试会红，但红的表面原因是「期望文件被 lint 蹭过」，会被误诊成模板改坏了。
// 同 generator/stubs 的写法，'fixtures' 相对每个 in() 目录解析：app/fixtures 等并不存在，
// 只会命中 tests/fixtures，不会误伤其它目录。
$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__ . '/app', __DIR__ . '/core', __DIR__ . '/tests', __DIR__ . '/scripts'])
    ->exclude(['generator/stubs', 'fixtures']);

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'declare_strict_types' => true,
        'no_unused_imports' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder);
