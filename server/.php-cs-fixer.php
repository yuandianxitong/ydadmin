<?php

$finder = PhpCsFixer\Finder::create()->in([__DIR__ . '/app', __DIR__ . '/core', __DIR__ . '/tests', __DIR__ . '/scripts']);

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
