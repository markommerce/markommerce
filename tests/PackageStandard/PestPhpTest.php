<?php

declare(strict_types=1);

$packagesDir = __DIR__ . '/../../packages';

$canonicalPestPhp = <<<'PHP'
<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/
PHP . "\n";

it('asserts every package with a tests/ directory has a tests/Pest.php file', function () use ($packagesDir): void {
    $packages = glob($packagesDir . '/*', GLOB_ONLYDIR) ?: [];

    expect($packages)->not->toBeEmpty();

    foreach ($packages as $packageDir) {
        $testsDir = $packageDir . '/tests';

        if (!is_dir($testsDir)) {
            continue;
        }

        expect($testsDir . '/Pest.php')
            ->toBeFile(basename($packageDir) . ' has a tests/ directory but is missing tests/Pest.php');
    }
});

it('asserts every tests/Pest.php content matches the canonical skeleton from package-standard.md', function () use ($packagesDir, $canonicalPestPhp): void {
    $packages = glob($packagesDir . '/*', GLOB_ONLYDIR) ?: [];

    expect($packages)->not->toBeEmpty();

    foreach ($packages as $packageDir) {
        $pestFile = $packageDir . '/tests/Pest.php';

        if (!is_file($pestFile)) {
            continue;
        }

        $actual = file_get_contents($pestFile);

        expect($actual)
            ->toBe($canonicalPestPhp, basename($packageDir) . '/tests/Pest.php does not match the canonical skeleton');
    }
});

it('asserts the core package has a tests/ directory and tests/Pest.php (library packages follow the same testing convention)', function () use ($canonicalPestPhp): void {
    $coreDir = __DIR__ . '/../../packages/core';

    expect($coreDir . '/tests')
        ->toBeDirectory('core package is missing a tests/ directory');

    expect($coreDir . '/tests/Pest.php')
        ->toBeFile('core package is missing tests/Pest.php');

    $actual = file_get_contents($coreDir . '/tests/Pest.php');

    expect($actual)
        ->toBe($canonicalPestPhp, 'core/tests/Pest.php does not match the canonical skeleton');
});
