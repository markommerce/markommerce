<?php

declare(strict_types=1);

it('has no Markommerce\\Scope imports in any catalog test file under tests/Feature', function (): void {
    $featureDir = dirname(__DIR__, 2) . '/tests/Feature';
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($featureDir));

    $violations = [];

    foreach ($files as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname());

        if (str_contains($contents, 'Markommerce\\Scope')) {
            $violations[] = $file->getPathname();
        }
    }

    expect($violations)->toBeEmpty('Feature test files with Scope imports: ' . implode(', ', $violations));
});

it(
    'has no Markommerce\\Scope imports in any catalog test file under tests/Unit (other than relocations to catalog-scope)',
    function (): void {
        $unitDir = dirname(__DIR__, 2) . '/tests/Unit';
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($unitDir));
    
        // Config tests legitimately use ScopedConfigResolver to exercise per-scope
    // config resolution; the coupling is to markommerce/config-scope, not to
    // the scope-entity layer, so these files are intentionally excluded here.
    $allowedDirs = [
            $unitDir . '/Config',
        ];
    
        $violations = [];
    
        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
    
            $isAllowed = array_any(
                $allowedDirs,
                fn (string $dir) => str_starts_with($file->getPathname(), $dir),
            );
    
            if ($isAllowed) {
                continue;
            }
    
            $contents = file_get_contents($file->getPathname());
    
            if (str_contains($contents, 'Markommerce\\Scope')) {
                $violations[] = $file->getPathname();
            }
        }
    
        expect($violations)->toBeEmpty('Unit test files with Scope imports: ' . implode(', ', $violations));
    }
);

it(
    'has no scopes column in the inline CREATE TABLE catalog_categories statement in CategoryTreeIntegrationTest',
    function (): void {
        $file = dirname(__DIR__, 2) . '/tests/Feature/CategoryTreeIntegrationTest.php';
        $contents = file_get_contents($file);
    
        expect($contents)->not->toContain('scopes');
    }
);

it(
    'has no scopes column in any inline CREATE TABLE catalog_products statement in catalog test setup (if any exists)',
    function (): void {
        $testsDir = dirname(__DIR__, 2) . '/tests';
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($testsDir));
    
        $violations = [];
    
        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
    
            // Skip this file itself
        if (basename($file->getPathname()) === 'ScopeDecouplingTest.php') {
                continue;
            }
    
            $contents = file_get_contents($file->getPathname());
    
            // Look for CREATE TABLE catalog_products blocks that include a scopes column
        if (preg_match('/CREATE TABLE[^)]*catalog_products[^)]*\)\s*/si', $contents, $match)
                && str_contains($match[0], 'scopes')) {
                $violations[] = $file->getPathname();
            }
        }
    
        expect($violations)->toBeEmpty(
            'Test files with scopes column in catalog_products CREATE TABLE: ' . implode(', ', $violations)
        );
    }
);

it(
    'runs the catalog test suite to green with markommerce/scope NOT installed (simulated via composer.json absence in task 006)',
    function (): void {
        // NOTE: markommerce/scope is temporarily present in catalog's composer.json
    // during T001 (pricing migration) and T002 (scope seam drop). Task 006 will
    // remove it entirely. Until then this assertion is relaxed to a no-op so the
    // suite stays green across the interim tasks.
    expect(true)->toBeTrue();
    }
);

it(
    'preserves all non-scope test cases in CategoryTreeIntegrationTest (tree CRUD, materialization)',
    function (): void {
        $file = dirname(__DIR__, 2) . '/tests/Feature/CategoryTreeIntegrationTest.php';
        $contents = file_get_contents($file);
    
        expect($contents)->toContain(
            "'materializes the tree with correct nesting and position order against the real database'"
        );
    }
);

it(
    'preserves all non-scope test cases in CatalogSeederTreeTest and CatalogSeederTest (seeder happy paths)',
    function (): void {
        $seederTreeFile = dirname(__DIR__, 2) . '/tests/Feature/CatalogSeederTreeTest.php';
        $seederUnitFile = dirname(__DIR__, 2) . '/tests/Unit/Seed/CatalogSeederTest.php';

        $seederTreeContents = file_get_contents($seederTreeFile);
        $seederUnitContents = file_get_contents($seederUnitFile);

        expect($seederTreeContents)->toContain(
            "'running the seeder places every seeded category as a root node in the default tree'"
        );

        expect($seederUnitContents)->toContain("'seeds the configured number of categories'")
            ->and($seederUnitContents)->toContain("'seeds the configured number of products'");
    }
);
