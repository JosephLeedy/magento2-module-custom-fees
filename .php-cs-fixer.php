<?php

/**
 * PHP Coding Standards Fixer configuration
 */

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$finder = Finder::create()
    ->in(__DIR__)
    ->name('*.phtml')
    ->exclude('vendor');

$config = new Config();

$config
    ->setFinder($finder)
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setRiskyAllowed(true)
    ->setRules(
        [
            '@PER-CS2.0' => true,
            'include' => true,
            'no_empty_statement' => true,
            'no_leading_namespace_whitespace' => true,
            'no_singleline_whitespace_before_semicolons' => true,
            'no_trailing_comma_in_singleline' => true,
            'no_unused_imports' => true,
            'object_operator_without_whitespace' => true,
            'standardize_not_equals' => true,
            'static_lambda' => true,
            'types_spaces' => [
                'space' => 'none',
                'space_multiple_catch' => 'single',
            ],
        ],
    )->setCacheFile(__DIR__ . '/.tmp/php-cs-fixer/.php-cs-fixer.cache');

return $config;
