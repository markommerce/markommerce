<?php

declare(strict_types=1);

$packagesDir = __DIR__ . '/../../packages';

it('asserts every packages/*/composer.json has "license": "MIT"', function () use ($packagesDir): void {
    $packages = glob($packagesDir . '/*', GLOB_ONLYDIR) ?: [];

    expect($packages)->not->toBeEmpty();

    foreach ($packages as $packageDir) {
        $content = file_get_contents($packageDir . '/composer.json');
        assert(is_string($content));
        $composerJson = json_decode($content, true);

        expect($composerJson['license'])
            ->toBe('MIT', basename($packageDir) . '/composer.json must have "license": "MIT"');
    }
});

it('asserts every packages/*/composer.json has "type" set to either marko-module or library', function () use ($packagesDir): void {
    $packages = glob($packagesDir . '/*', GLOB_ONLYDIR) ?: [];

    expect($packages)->not->toBeEmpty();

    foreach ($packages as $packageDir) {
        $content = file_get_contents($packageDir . '/composer.json');
        assert(is_string($content));
        $composerJson = json_decode($content, true);

        expect($composerJson['type'])
            ->toBeIn(['marko-module', 'library'], basename($packageDir) . '/composer.json must have "type" set to marko-module or library');
    }
});

it('asserts every packages/*/composer.json autoload.psr-4 has exactly one key mapping to src/ AND optionally one additional key mapping to Seed/ (for packages shipping seeders) — no other roots allowed', function () use ($packagesDir): void {
    $packages = glob($packagesDir . '/*', GLOB_ONLYDIR) ?: [];

    expect($packages)->not->toBeEmpty();

    foreach ($packages as $packageDir) {
        $content = file_get_contents($packageDir . '/composer.json');
        assert(is_string($content));
        $composerJson = json_decode($content, true);
        $psr4 = $composerJson['autoload']['psr-4'] ?? [];
        $pkg = basename($packageDir);

        $srcRoots = array_filter($psr4, fn(string $path): bool => $path === 'src/');
        $seedRoots = array_filter($psr4, fn(string $path): bool => $path === 'Seed/');
        $otherRoots = array_filter($psr4, fn(string $path): bool => $path !== 'src/' && $path !== 'Seed/');

        expect(count($srcRoots))
            ->toBe(1, "$pkg/composer.json autoload.psr-4 must have exactly one key mapping to src/");

        expect($otherRoots)
            ->toBeEmpty("$pkg/composer.json autoload.psr-4 may only have keys mapping to src/ or Seed/");

        expect(count($seedRoots))
            ->toBeLessThanOrEqual(1, "$pkg/composer.json autoload.psr-4 may have at most one key mapping to Seed/");
    }
});

it('asserts every packages/*/composer.json autoload-dev.psr-4 has exactly one key mapping to tests/ (when the package has tests)', function () use ($packagesDir): void {
    $packages = glob($packagesDir . '/*', GLOB_ONLYDIR) ?: [];

    expect($packages)->not->toBeEmpty();

    foreach ($packages as $packageDir) {
        $testsDir = $packageDir . '/tests';

        if (!is_dir($testsDir)) {
            continue;
        }

        $content = file_get_contents($packageDir . '/composer.json');
        assert(is_string($content));
        $composerJson = json_decode($content, true);
        $psr4Dev = $composerJson['autoload-dev']['psr-4'] ?? [];
        $pkg = basename($packageDir);

        $testsRoots = array_filter($psr4Dev, fn(string $path): bool => $path === 'tests/');

        expect(count($testsRoots))
            ->toBe(1, "$pkg/composer.json autoload-dev.psr-4 must have exactly one key mapping to tests/");

        expect(count($psr4Dev))
            ->toBe(1, "$pkg/composer.json autoload-dev.psr-4 must have exactly one entry (mapping to tests/)");
    }
});

it('asserts every packages/*/composer.json that uses Pest has config.allow-plugins."pestphp/pest-plugin" set to true', function () use ($packagesDir): void {
    $packages = glob($packagesDir . '/*', GLOB_ONLYDIR) ?: [];

    expect($packages)->not->toBeEmpty();

    foreach ($packages as $packageDir) {
        $testsDir = $packageDir . '/tests';

        if (!is_dir($testsDir)) {
            continue;
        }

        $content = file_get_contents($packageDir . '/composer.json');
        assert(is_string($content));
        $composerJson = json_decode($content, true);
        $pkg = basename($packageDir);

        expect($composerJson['config']['allow-plugins']['pestphp/pest-plugin'] ?? null)
            ->toBeTrue("$pkg/composer.json must have config.allow-plugins.pestphp/pest-plugin set to true (package uses Pest)");
    }
});

it('asserts every marko-module package with a tests/ directory has marko/testing in require-dev', function () use ($packagesDir): void {
    $packages = glob($packagesDir . '/*', GLOB_ONLYDIR) ?: [];

    expect($packages)->not->toBeEmpty();

    foreach ($packages as $packageDir) {
        $content = file_get_contents($packageDir . '/composer.json');
        assert(is_string($content));
        $composerJson = json_decode($content, true);
        $pkg = basename($packageDir);

        if (($composerJson['type'] ?? '') !== 'marko-module') {
            continue;
        }

        $testsDir = $packageDir . '/tests';

        if (!is_dir($testsDir)) {
            continue;
        }

        $requireDev = $composerJson['require-dev'] ?? [];

        expect(array_key_exists('marko/testing', $requireDev))
            ->toBeTrue("$pkg/composer.json must have marko/testing in require-dev (marko-module with tests/)");
    }
});

it('asserts every package with tests/ has pestphp/pest in require-dev', function () use ($packagesDir): void {
    $packages = glob($packagesDir . '/*', GLOB_ONLYDIR) ?: [];

    expect($packages)->not->toBeEmpty();

    foreach ($packages as $packageDir) {
        $testsDir = $packageDir . '/tests';

        if (!is_dir($testsDir)) {
            continue;
        }

        $content = file_get_contents($packageDir . '/composer.json');
        assert(is_string($content));
        $composerJson = json_decode($content, true);
        $pkg = basename($packageDir);
        $requireDev = $composerJson['require-dev'] ?? [];

        expect(array_key_exists('pestphp/pest', $requireDev))
            ->toBeTrue("$pkg/composer.json must have pestphp/pest in require-dev (package has tests/)");
    }
});
