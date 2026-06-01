<?php

declare(strict_types=1);

/**
 * PHP-CS-Fixer config for parisek/twig-typography.
 *
 * The codebase already follows PER-CS (4-space indent, next-line braces,
 * no spaces inside parens), so `@PER-CS` formalises the existing style with
 * minimal churn.
 */

$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/tests'])
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS' => true,
        'declare_strict_types' => true,
        'no_unused_imports' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'single_quote' => true,
        'trailing_comma_in_multiline' => ['elements' => ['arrays', 'arguments', 'parameters']],
        'array_syntax' => ['syntax' => 'short'],
    ])
    ->setFinder($finder);
