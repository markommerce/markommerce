<?php

declare(strict_types=1);

/**
 * @return array<string, mixed>
 */
function readNpmManifest(string $path): array
{
    $contents = file_get_contents($path);
    expect($contents)->not->toBeFalse();
    /** @var string $contents */

    return json_decode($contents, true);
}

it('has a package.json declaring name @markommerce/frontend and type module', function (): void {
    $path = __DIR__ . '/../../package.json';

    expect(file_exists($path))->toBeTrue();

    $manifest = readNpmManifest($path);

    expect($manifest['name'])->toBe('@markommerce/frontend');
    expect($manifest['type'])->toBe('module');
});

it('sets the markommerce.extension to ./resources/js/index.ts', function (): void {
    $manifest = readNpmManifest(__DIR__ . '/../../package.json');

    expect($manifest['markommerce']['extension'])->toBe('./resources/js/index.ts');
});

it('sets markommerce.priority to 0 marking this as the kernel', function (): void {
    $manifest = readNpmManifest(__DIR__ . '/../../package.json');

    expect($manifest['markommerce']['priority'])->toBe(0);
});

it('declares lit and open-props as peer dependencies with the agreed version ranges', function (): void {
    $manifest = readNpmManifest(__DIR__ . '/../../package.json');

    expect($manifest['peerDependencies']['lit'])->toBe('^3.0');
    expect($manifest['peerDependencies']['open-props'])->toBe('^1.7');
});

it('declares vitest, happy-dom, typescript, and lit as dev dependencies', function (): void {
    $manifest = readNpmManifest(__DIR__ . '/../../package.json');

    expect(array_key_exists('vitest', $manifest['devDependencies']))->toBeTrue();
    expect(array_key_exists('happy-dom', $manifest['devDependencies']))->toBeTrue();
    expect(array_key_exists('typescript', $manifest['devDependencies']))->toBeTrue();
    expect(array_key_exists('lit', $manifest['devDependencies']))->toBeTrue();
});

it('exposes resources/js/index.ts as the package main field', function (): void {
    $manifest = readNpmManifest(__DIR__ . '/../../package.json');

    expect($manifest['main'])->toBe('./resources/js/index.ts');
});

it('exports . pointing at ./resources/js/index.ts for downstream imports', function (): void {
    $manifest = readNpmManifest(__DIR__ . '/../../package.json');

    expect($manifest['exports']['.'])->toBe('./resources/js/index.ts');
});

it('creates an empty resources/js/index.ts with the strict-mode preamble comment', function (): void {
    $path = __DIR__ . '/../../resources/js/index.ts';

    expect(file_exists($path))->toBeTrue();

    $contents = file_get_contents($path);
    expect($contents)->not->toBeFalse();
    expect($contents)->toContain('// Kernel entry');
});

it('npm install at the repo root resolves @markommerce/frontend via the workspace', function (): void {
    $lockPath = __DIR__ . '/../../../../package-lock.json';

    expect(file_exists($lockPath))->toBeTrue(
        'package-lock.json does not exist — run npm install at the repo root',
    );

    $lockContents = file_get_contents($lockPath);
    expect($lockContents)->not->toBeFalse();
    /** @var string $lockContents */

    $lock = json_decode($lockContents, true);

    expect(isset($lock['packages']['node_modules/@markommerce/frontend']))->toBeTrue(
        '@markommerce/frontend not found in package-lock.json — run npm install at the repo root',
    );
});
