<?php

declare(strict_types=1);

it('ships a README that follows the project package README standards', function (): void {
    $readmePath = dirname(__DIR__) . '/README.md';

    expect(file_exists($readmePath))->toBeTrue();

    $content = file_get_contents($readmePath);

    expect($content)
        ->toContain('# markommerce/catalog-market')
        ->toContain('## Installation')
        ->toContain('composer require markommerce/catalog-market')
        ->toContain('## Quick Example')
        ->toContain('boot')
        ->toContain('ScopedFieldRegistry')
        ->toContain('## Documentation')
        ->toContain('markommerce.dev/docs/packages/catalog-market');
});

it('markommerce/catalog-market README documents the active market-scoped priceAmount registration', function (): void {
    $readmePath = dirname(__DIR__) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)
        ->toContain('priceAmount')
        ->toContain('market')
        ->toContain('Product');
});

it('markommerce/catalog-market README documents per-market price override and fallback behavior', function (): void {
    $readmePath = dirname(__DIR__) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)
        ->toContain('ScopeResolver')
        ->toContain('ProductScopedOverrides')
        ->toContain('setOverride');
});
