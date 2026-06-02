<?php

declare(strict_types=1);

it('marks the P3 row in the FEATURES.md Refactor phases table as completed', function (): void {
    $content = file_get_contents(__DIR__ . '/../../../FEATURES.md');

    // P3 row must show completed status
    expect($content)->toMatch('/\*\*P3\*\*.*?`completed`/s');
});

it('sets the P3 row\'s Plan / branch column to catalog-storefront-extract', function (): void {
    $content = file_get_contents(__DIR__ . '/../../../FEATURES.md');

    // P3 row must reference the catalog-storefront-extract branch
    expect($content)->toMatch('/\*\*P3\*\*.*?catalog-storefront-extract/s');
});

it('flips the P1 row to completed and fills the Plan / branch with scope-metadata-registry (P1 already merged on develop)', function (): void {
    $content = file_get_contents(__DIR__ . '/../../../FEATURES.md');

    // P1 row must show completed status and the correct branch
    expect($content)->toMatch('/\*\*P1\*\*.*?`completed`/s');
    expect($content)->toMatch('/\*\*P1\*\*.*?scope-metadata-registry/s');
});

it('flips the P2 row to completed and fills the Plan / branch with catalog-scope-decouple (P2 already merged on develop)', function (): void {
    $content = file_get_contents(__DIR__ . '/../../../FEATURES.md');

    // P2 row must show completed status and the correct branch
    expect($content)->toMatch('/\*\*P2\*\*.*?`completed`/s');
    expect($content)->toMatch('/\*\*P2\*\*.*?catalog-scope-decouple/s');
});

it('adds markommerce/catalog-storefront-scope to the Proposed new packages table with a description that mentions the Preference mechanism', function (): void {
    $content = file_get_contents(__DIR__ . '/../../../FEATURES.md');

    // catalog-storefront-scope must appear in the Proposed new packages section
    expect($content)->toContain('markommerce/catalog-storefront-scope');

    // The description must mention the Preference mechanism
    expect($content)->toMatch('/catalog-storefront-scope.*?Preference/s');
});

it('notes that Tier 2 storefront merchants additionally install markommerce/catalog-storefront-scope on top of the Tier 2 headless stack', function (): void {
    $content = file_get_contents(__DIR__ . '/../../../FEATURES.md');

    // Tier 2 section must mention catalog-storefront-scope for storefront merchants
    $tier2Section = preg_replace('/.*### Tier 2/s', '### Tier 2', $content);
    $tier2Section = preg_replace('/### Tier 3.*/s', '', $tier2Section ?? $content);

    expect($tier2Section)->toContain('catalog-storefront-scope');
});

it('updates the package count rollup to reflect markommerce/catalog-storefront-scope being available for the Tier 2 storefront variant', function (): void {
    $content = file_get_contents(__DIR__ . '/../../../FEATURES.md');

    // Package count section must mention 15 packages for Tier 2 storefront variant
    expect($content)->toContain('15');
    // And catalog-storefront-scope must appear in the count context
    expect($content)->toMatch('/15.*catalog-storefront-scope|catalog-storefront-scope.*15/s');
});

it('leaves the P4 row in the FEATURES.md Refactor phases table as completed', function (): void {
    $content = file_get_contents(__DIR__ . '/../../../FEATURES.md');

    // P4 was completed in the catalog-market-extract phase
    expect($content)->toMatch('/\*\*P4\*\*.*?`completed`/s');
    expect($content)->toMatch('/\*\*P4\*\*.*?catalog-market-extract/s');
});
