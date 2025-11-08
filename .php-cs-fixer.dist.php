<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([
        __DIR__ . '/app',
        __DIR__ . '/config',
        __DIR__ . '/routes',
        __DIR__ . '/database',
        __DIR__ . '/tests',
    ])
    ->name('*.php')
    ->notName('*.blade.php')
    ->ignoreVCS(true)
    ->ignoreDotFiles(true)
;

return (new Config())
    ->setRules([
        '@PSR12' => true,
        '@PhpCsFixer' => true,
        'array_syntax' => ['syntax' => 'short'],
        'single_quote' => true,
        'no_unused_imports' => true,
        'trailing_comma_in_multiline' => ['after_heredoc' => true],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'blank_line_after_namespace' => true,
        'blank_line_after_opening_tag' => true,
        'no_extra_blank_lines' => ['tokens' => ['extra']],
        'concat_space' => ['spacing' => 'one'],
        'phpdoc_align' => ['align' => 'left'],
        'phpdoc_no_empty_return' => false,
        'phpdoc_summary' => false, 'yoda_style' => false,
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder);
