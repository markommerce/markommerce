<?php

declare(strict_types=1);

it(
    'contains expected Unit test file packages/scope-pgsql/tests/Unit/Query/PgSqlScopeSortRendererTest.php',
    function (): void {
        // __DIR__ = packages/scope-pgsql/tests/Unit
        expect(file_exists(__DIR__ . '/Query/PgSqlScopeSortRendererTest.php'))->toBeTrue(
            'Missing: packages/scope-pgsql/tests/Unit/Query/PgSqlScopeSortRendererTest.php',
        );
    },
);

it(
    'contains expected Feature test file packages/scope-pgsql/tests/Feature/AutoMigrationTest.php',
    function (): void {
        // dirname(__DIR__) = packages/scope-pgsql/tests
        $localTestsFeature = dirname(__DIR__) . '/Feature';

        expect(file_exists($localTestsFeature . '/AutoMigrationTest.php'))->toBeTrue(
            'Missing: packages/scope-pgsql/tests/Feature/AutoMigrationTest.php',
        );
    },
);

it('has no remaining upstream-namespace references in packages/scope-pgsql/tests/', function (): void {
    // dirname(__DIR__) = packages/scope-pgsql/tests
    $testsDir = dirname(__DIR__);
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($testsDir),
    );

    $matches = [];
    foreach ($files as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        // Skip this file itself (it contains grep-pattern assertions)
        if ($file->getPathname() === __FILE__) {
            continue;
        }

        // Skip SourceTree test (it contains grep assertions for the src dir)
        if (str_contains($file->getPathname(), 'SourceTree')) {
            continue;
        }

        $contents = file_get_contents($file->getPathname());

        // Detect the old upstream namespace (single-backslash form, excluding Markommerce prefix)
        if (preg_match('/(?<!Markommerc)e\\\\Scope\\\\/', $contents)) {
            $matches[] = $file->getPathname() . ' (single-backslash old-namespace)';
        }

        // Detect the old upstream namespace (double-backslash form in string literals)
        if (preg_match('/Marko\\\\\\\\Scope\\\\\\\\/', $contents)) {
            $matches[] = $file->getPathname() . ' (double-backslash old-namespace)';
        }
    }

    expect($matches)->toBe([]);
});

it('the AutoMigrationTest carries no ->group(\'integration-destructive\') tag', function (): void {
    // dirname(__DIR__) = packages/scope-pgsql/tests
    $path = dirname(__DIR__) . '/Feature/AutoMigrationTest.php';

    if (!file_exists($path)) {
        $this->markTestSkipped('AutoMigrationTest.php not yet created');
    }

    $contents = file_get_contents($path);

    expect($contents)->not->toContain('->group(\'integration-destructive\')')
        ->and($contents)->not->toContain('->group("integration-destructive")');
});
