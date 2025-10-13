<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in(['src', 'tests'])
    ->exclude(['vendor', 'var']);

return (new Config())
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'single_quote' => true,
        'no_unused_imports' => true,
        'no_trailing_whitespace' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
    ])
    ->setRiskyAllowed(true)
    ->setUsingCache(true)
    ->setFinder($finder);
