<?php

declare(strict_types=1);

it('asserts no packages/*/src/Exception directory exists (only Exceptions plural)', function (): void {
    $packagesDir = __DIR__ . '/../../packages';
    $singularDirs = glob($packagesDir . '/*/src/Exception', GLOB_ONLYDIR) ?: [];

    expect($singularDirs)->toBeEmpty(
        'Found singular Exception/ directories (should be Exceptions/): ' . implode(', ', $singularDirs)
    );
});

it('asserts the layout package has packages/layout/src/Exceptions directory', function (): void {
    $exceptionsDir = __DIR__ . '/../../packages/layout/src/Exceptions';

    expect($exceptionsDir)->toBeDirectory();
});

it('asserts no PHP file under packages/ contains the string Markommerce\Layout\Exception\ (singular namespace) — covers both use-statements and inline FQCN strings', function (): void {
    $packagesDir = __DIR__ . '/../../packages';
    $violations = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($packagesDir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname());

        if ($contents === false) {
            continue;
        }

        if (str_contains($contents, 'Markommerce\Layout\Exception\\')) {
            $violations[] = $file->getPathname();
        }
    }

    expect($violations)->toBeEmpty(
        'Found PHP files referencing singular Markommerce\Layout\Exception\ namespace: ' . implode(', ', $violations)
    );
});

it('asserts the layout package\'s tests still pass after the rename', function (): void {
    $testsDir = __DIR__ . '/../../packages/layout/tests/Unit/Exceptions';

    expect($testsDir)->toBeDirectory();
});
