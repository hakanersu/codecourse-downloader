<?php

$finder = PhpCsFixer\Finder::create()
    ->name('*.php')
    ->in(__DIR__.DIRECTORY_SEPARATOR.'src');

return PhpCsFixer\Config::create()
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR2' => true,
        '@Symfony' => true,
        'align_multiline_comment' => ['comment_type' => 'all_multiline'],
        'array_syntax' => ['syntax' => 'short'],
        'concat_space' => ['spacing' => 'one'],
        'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline'],
        'not_operator_with_successor_space' => true,
        'ordered_imports' => ['sortAlgorithm' => 'length'],
        'phpdoc_annotation_without_dot' => true,
        'phpdoc_order' => true,
        'strict_param' => true,
        'trailing_comma_in_multiline_array' => true,
        'yoda_style' => false,
    ])
    ->setFinder($finder);