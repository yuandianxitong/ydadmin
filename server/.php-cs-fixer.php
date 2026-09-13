<?php

// core/generator/stubs 下的 *.stub.php 是代码生成器的模板文件，不是真正的 PHP 文件
// （里面天然会出现 use app\...、未闭合的类片段等写法），cs-fixer 不该格式化它们。
$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__ . '/app', __DIR__ . '/core', __DIR__ . '/tests', __DIR__ . '/scripts'])
    ->notName('*.stub.php');

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
