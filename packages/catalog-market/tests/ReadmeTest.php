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

it('markommerce/catalog-market README declares its placeholder status with a Placeholder status section', function (): void {
    $readmePath = dirname(__DIR__) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)
        ->toContain('## Placeholder Status')
        ->toContain('empty boot closure')
        ->toContain('price')
        ->toContain('visibility');
});

it('markommerce/catalog-market README cross-links to FEATURES.md for the planned end state', function (): void {
    $readmePath = dirname(__DIR__) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)->toContain('FEATURES.md');
});
