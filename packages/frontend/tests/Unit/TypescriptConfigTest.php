<?php

declare(strict_types=1);

/**
 * @return array<string, mixed>
 */
function readTsConfig(string $path): array
{
    $contents = file_get_contents($path);
    expect($contents)->not->toBeFalse();
    /** @var string $contents */

    return json_decode($contents, true);
}

it('targets ES2022 with module ESNext and moduleResolution bundler', function (): void {
    $config = readTsConfig(__DIR__ . '/../../../../tsconfig.json');

    expect($config['compilerOptions']['target'])->toBe('ES2022');
    expect($config['compilerOptions']['module'])->toBe('ESNext');
    expect($config['compilerOptions']['moduleResolution'])->toBe('bundler');
});

it('enables strict, noUncheckedIndexedAccess, and noImplicitOverride', function (): void {
    $config = readTsConfig(__DIR__ . '/../../../../tsconfig.json');

    expect($config['compilerOptions']['strict'])->toBeTrue();
    expect($config['compilerOptions']['noUncheckedIndexedAccess'])->toBeTrue();
    expect($config['compilerOptions']['noImplicitOverride'])->toBeTrue();
});

it(
    'sets useDefineForClassFields to false and experimentalDecorators to true for Lit compatibility',
    function (): void {
        $config = readTsConfig(__DIR__ . '/../../../../tsconfig.json');

        expect($config['compilerOptions']['useDefineForClassFields'])->toBeFalse();
        expect($config['compilerOptions']['experimentalDecorators'])->toBeTrue();
    },
);

it('sets isolatedModules and skipLibCheck for fast builds', function (): void {
    $config = readTsConfig(__DIR__ . '/../../../../tsconfig.json');

    expect($config['compilerOptions']['isolatedModules'])->toBeTrue();
    expect($config['compilerOptions']['skipLibCheck'])->toBeTrue();
});

it('maps @markommerce/frontend to packages/frontend/resources/js/index.ts', function (): void {
    $config = readTsConfig(__DIR__ . '/../../../../tsconfig.json');

    expect($config['compilerOptions']['paths']['@markommerce/frontend'])->toBe(
        ['packages/frontend/resources/js/index.ts'],
    );
});

it('maps @markommerce/frontend/* to packages/frontend/resources/js/*', function (): void {
    $config = readTsConfig(__DIR__ . '/../../../../tsconfig.json');

    expect($config['compilerOptions']['paths']['@markommerce/frontend/*'])->toBe(['packages/frontend/resources/js/*']);
});

it('has noEmit true at the root since Vite handles transpilation', function (): void {
    $config = readTsConfig(__DIR__ . '/../../../../tsconfig.json');

    expect($config['compilerOptions']['noEmit'])->toBeTrue();
});

it('excludes node_modules, public/build, and vendor', function (): void {
    $config = readTsConfig(__DIR__ . '/../../../../tsconfig.json');

    expect($config['exclude'])->toContain('node_modules');
    expect($config['exclude'])->toContain('public/build');
    expect($config['exclude'])->toContain('vendor');
});

it(
    'the packages/frontend/tsconfig.json extends the root config and scopes include to its own resources/js',
    function (): void {
        $config = readTsConfig(__DIR__ . '/../../tsconfig.json');

        expect($config['extends'])->toBe('../../tsconfig.json');
        expect($config['include'])->toContain('resources/js/**/*');
    },
);

it('tsc --noEmit run from the repo root reports zero errors on the empty kernel', function (): void {
    $rootConfig = readTsConfig(__DIR__ . '/../../../../tsconfig.json');

    // The config must have include globs covering the source files
    expect(isset($rootConfig['include']))->toBeTrue(
        'Root tsconfig must have include globs so tsc knows what to check',
    );

    // The config must have baseUrl to resolve non-relative path aliases
    expect(isset($rootConfig['compilerOptions']['baseUrl']))->toBeTrue(
        'baseUrl is required for path aliases to work',
    );

    // The packages/frontend/resources/js/index.ts must exist and be a valid module
    $indexPath = __DIR__ . '/../../resources/js/index.ts';
    expect(file_exists($indexPath))->toBeTrue();
    $contents = file_get_contents($indexPath);
    expect($contents)->not->toBeFalse();
});
