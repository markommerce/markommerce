<?php

declare(strict_types=1);

it(
    'contains expected Unit test file packages/scope-pgsql/tests/Unit/Query/PgSqlScopedFieldRendererTest.php',
    function (): void {
        // __DIR__ = packages/scope-pgsql/tests/Unit
        expect(file_exists(__DIR__ . '/Query/PgSqlScopedFieldRendererTest.php'))->toBeTrue(
            'Missing: packages/scope-pgsql/tests/Unit/Query/PgSqlScopedFieldRendererTest.php',
        );
    },
);

it(
    'contains expected Unit test file packages/scope-pgsql/tests/Unit/AutoMigrationTest.php',
    function (): void {
        // __DIR__ = packages/scope-pgsql/tests/Unit
        expect(file_exists(__DIR__ . '/AutoMigrationTest.php'))->toBeTrue(
            'Missing: packages/scope-pgsql/tests/Unit/AutoMigrationTest.php',
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
    // __DIR__ = packages/scope-pgsql/tests/Unit
    $path = __DIR__ . '/AutoMigrationTest.php';

    if (!file_exists($path)) {
        $this->markTestSkipped('AutoMigrationTest.php not yet created');
    }

    $contents = file_get_contents($path);

    expect($contents)->not->toContain('->group(\'integration-destructive\')')
        ->and($contents)->not->toContain('->group("integration-destructive")');
});
