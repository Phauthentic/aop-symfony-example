<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/lib/aop/src',
        __DIR__ . '/lib/aop-bundle/src',
        __DIR__ . '/lib/aop/tests',
        __DIR__ . '/lib/aop-bundle/tests',
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        '@Symfony' => true,
        'declare_strict_types' => true,
    ])
    ->setFinder($finder);
