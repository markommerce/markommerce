<?php

declare(strict_types=1);

it('flips the P5 row\'s status from pending to completed in FEATURES.md', function (): void {
    $content = (string) file_get_contents(__DIR__ . '/../../../FEATURES.md');

    expect($content)->toMatch('/\*\*P5\*\*.*?`completed`/s');
});

it('sets the P5 row\'s plan field to config-scope-decouple in FEATURES.md', function (): void {
    $content = (string) file_get_contents(__DIR__ . '/../../../FEATURES.md');

    expect($content)->toMatch('/\*\*P5\*\*.*?config-scope-decouple/s');
});

it('updates the Tier 2 headless package count to 14 in FEATURES.md', function (): void {
    $content = (string) file_get_contents(__DIR__ . '/../../../FEATURES.md');

    expect($content)->toMatch('/Tier 2 headless.*?14 packages|14 packages.*?Tier 2 headless/s');
});

it('updates the Tier 2 storefront package count to 15 in FEATURES.md', function (): void {
    $content = (string) file_get_contents(__DIR__ . '/../../../FEATURES.md');

    expect($content)->toMatch('/Tier 2 storefront.*?15 packages|15 packages.*?Tier 2 storefront/s');
});

it('updates the Tier 3 package count to 18 in FEATURES.md', function (): void {
    $content = (string) file_get_contents(__DIR__ . '/../../../FEATURES.md');

    expect($content)->toMatch('/Tier 3.*?18 packages|18 packages.*?Tier 3/s');
});

it(
    'removes the 🆕 marker from config-scope, config-locale, and config-market in the Proposed new packages tables',
    function (): void {
        $content = (string) file_get_contents(__DIR__ . '/../../../FEATURES.md');
    
        expect($content)->not->toContain('🆕 markommerce/config-scope`');
        expect($content)->not->toContain('🆕 markommerce/config-locale');
        expect($content)->not->toContain('🆕 markommerce/config-market');
    }
);

it('notes that config-pgsql no longer emits an overrides JSONB column in the Tier 1 row', function (): void {
    $content = (string) file_get_contents(__DIR__ . '/../../../FEATURES.md');

    $tier1Section = (string) preg_replace('/.*### Tier 1/s', '### Tier 1', $content);
    $tier1Section = (string) preg_replace('/### Tier 2.*/s', '', $tier1Section);

    expect($tier1Section)->toContain('overrides');
});
