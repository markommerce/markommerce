<?php

declare(strict_types=1);

it('it documents the quick-start for writing an integration test', function (): void {
    $docsPath = dirname(__DIR__, 4) . '/docs/src/content/docs/packages/testing.md';
    $content = file_get_contents($docsPath);

    expect($content)
        ->toContain('IntegrationTestCase')
        ->toContain('StoreProfile')
        ->toContain('setUpIntegration')
        ->toContain('tearDownIntegration');
});

it('it documents the three named store profiles', function (): void {
    $docsPath = dirname(__DIR__, 4) . '/docs/src/content/docs/packages/testing.md';
    $content = file_get_contents($docsPath);

    expect($content)
        ->toContain('StoreProfile::simple(')
        ->toContain('StoreProfile::singleMarketTwoLocales(')
        ->toContain('StoreProfile::twoMarketsTwoLocales(');
});

it('it documents fromInstalled for merchant developers', function (): void {
    $docsPath = dirname(__DIR__, 4) . '/docs/src/content/docs/packages/testing.md';
    $content = file_get_contents($docsPath);

    expect($content)
        ->toContain('fromInstalled')
        ->toContain('appConfigPath')
        ->toContain('MARKO_APP_CONFIG_PATH');
});

it('it documents the isolation model and truncate opt-out', function (): void {
    $docsPath = dirname(__DIR__, 4) . '/docs/src/content/docs/packages/testing.md';
    $content = file_get_contents($docsPath);

    expect($content)
        ->toContain('IsolationMode')
        ->toContain('Rollback')
        ->toContain('Truncate');
});

it('it documents the storeProfiles invariant-matrix dataset', function (): void {
    $docsPath = dirname(__DIR__, 4) . '/docs/src/content/docs/packages/testing.md';
    $content = file_get_contents($docsPath);

    expect($content)
        ->toContain('storeProfiles')
        ->toContain("->with('storeProfiles')")
        ->toContain('InvariantMatrixTest');
});

it('it follows the package README standard', function (): void {
    $readmePath = dirname(__DIR__, 2) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)
        ->toContain('# markommerce/testing')
        ->toContain('## Installation')
        ->toContain('composer require --dev markommerce/testing')
        ->toContain('## Quick Example')
        ->toContain('## Documentation')
        ->toContain('markommerce.dev/docs/packages/testing');
});
