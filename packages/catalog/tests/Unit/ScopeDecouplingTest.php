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

it('has no Markommerce\\Scope imports in any catalog test file under tests/Unit (other than relocations to catalog-scope)', function (): void {
    $unitDir = dirname(__DIR__, 2) . '/tests/Unit';
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($unitDir));

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

    expect($violations)->toBeEmpty('Unit test files with Scope imports: ' . implode(', ', $violations));
});

it('has no scopes column in the inline CREATE TABLE catalog_categories statement in CategoryTreeIntegrationTest', function (): void {
    $file = dirname(__DIR__, 2) . '/tests/Feature/CategoryTreeIntegrationTest.php';
    $contents = file_get_contents($file);

    expect($contents)->not->toContain('scopes');
});

it('has no scopes column in any inline CREATE TABLE catalog_products statement in catalog test setup (if any exists)', function (): void {
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

    expect($violations)->toBeEmpty('Test files with scopes column in catalog_products CREATE TABLE: ' . implode(', ', $violations));
});

it('runs the catalog test suite to green with markommerce/scope NOT installed (simulated via composer.json absence in task 006)', function (): void {
    // Verify catalog's composer.json does not require markommerce/scope
    $manifest = json_decode(
        file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
        true,
    );

    $require = $manifest['require'] ?? [];
    $requireDev = $manifest['require-dev'] ?? [];

    expect($require)->not->toHaveKey('markommerce/scope');
    expect($requireDev)->not->toHaveKey('markommerce/scope');
});

it('preserves all non-scope test cases in CategoryControllerTest (controller wiring, route resolution, response shape)', function (): void {
    $file = dirname(__DIR__, 2) . '/tests/Feature/CategoryControllerTest.php';
    $contents = file_get_contents($file);

    // These test descriptions must still exist in the file
    expect($contents)->toContain("'places a Get route at /catalog/category/{id} on the controller action'")
        ->and($contents)->toContain("'returns a 200 response with the assembled layout HTML when the category exists'")
        ->and($contents)->toContain("'returns a 404 response when the requested category id does not exist'")
        ->and($contents)->toContain("'includes the category name in the rendered page heading'")
        ->and($contents)->toContain("'renders every assigned product as a product grid item in the response body'");
});

it('preserves all non-scope test cases in CategoryLayoutTest (layout rendering, theme integration, raw product name display)', function (): void {
    $file = dirname(__DIR__, 2) . '/tests/Feature/CategoryLayoutTest.php';
    $contents = file_get_contents($file);

    expect($contents)->toContain("'defines a category_show layout for the CategoryController show action'")
        ->and($contents)->toContain("'compiles the category_show layout without error'")
        ->and($contents)->toContain("'renders the category page with a grid of product cards'")
        ->and($contents)->toContain("'returns 404 from the controller when the category does not exist'");
});

it('preserves all non-scope test cases in CategoryTreeIntegrationTest (tree CRUD, market assignment lifecycle)', function (): void {
    $file = dirname(__DIR__, 2) . '/tests/Feature/CategoryTreeIntegrationTest.php';
    $contents = file_get_contents($file);

    expect($contents)->toContain("'creates a non-default tree, places categories, assigns it to a market, and resolves the tree for that market'")
        ->and($contents)->toContain("'resolves the default tree for a market with no assignment'")
        ->and($contents)->toContain("'materializes the tree with correct nesting and position order against the real database'");
});

it('preserves all non-scope test cases in CatalogSeederTreeTest and CatalogSeederTest (seeder happy paths)', function (): void {
    $seederTreeFile = dirname(__DIR__, 2) . '/tests/Feature/CatalogSeederTreeTest.php';
    $seederUnitFile = dirname(__DIR__, 2) . '/tests/Unit/Seed/CatalogSeederTest.php';

    $seederTreeContents = file_get_contents($seederTreeFile);
    $seederUnitContents = file_get_contents($seederUnitFile);

    expect($seederTreeContents)->toContain("'running the seeder creates the default tree when none exists'")
        ->and($seederTreeContents)->toContain("'running the seeder reuses an existing default tree without creating a duplicate'");

    expect($seederUnitContents)->toContain("'seeds the configured number of categories'")
        ->and($seederUnitContents)->toContain("'seeds the configured number of products'");
});
