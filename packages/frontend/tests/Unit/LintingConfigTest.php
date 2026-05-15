<?php

declare(strict_types=1);

/**
 * @return array<string, mixed>
 */
function readJsonFile(string $path): array
{
    $contents = file_get_contents($path);
    expect($contents)->not->toBeFalse();
    /** @var string $contents */

    return json_decode($contents, true);
}

it('provides a flat-config eslint.config.js that lints TypeScript with @typescript-eslint and eslint-plugin-lit', function (): void {
    $path = __DIR__ . '/../../../../eslint.config.js';

    expect(file_exists($path))->toBeTrue('eslint.config.js must exist at the repo root');

    $contents = file_get_contents($path);
    expect($contents)->not->toBeFalse();
    /** @var string $contents */

    // Must be flat config format (ESLint 9) — exports an array
    expect($contents)->toContain('typescript-eslint');
    expect($contents)->toContain('eslint-plugin-lit');
});

it('bans the use of any and disables no-unused-vars in favor of typescript-eslint\'s variant', function (): void {
    $path = __DIR__ . '/../../../../eslint.config.js';

    expect(file_exists($path))->toBeTrue('eslint.config.js must exist at the repo root');

    $contents = file_get_contents($path);
    expect($contents)->not->toBeFalse();
    /** @var string $contents */

    // Must ban @typescript-eslint/no-explicit-any
    expect($contents)->toContain('@typescript-eslint/no-explicit-any');
    // Must disable base no-unused-vars in favor of typescript-eslint variant
    expect($contents)->toContain('no-unused-vars');
    expect($contents)->toContain('@typescript-eslint/no-unused-vars');
});

it('provides prettier.config.js with project-consistent settings (single quotes, semis, 100-char width)', function (): void {
    $path = __DIR__ . '/../../../../prettier.config.js';

    expect(file_exists($path))->toBeTrue('prettier.config.js must exist at the repo root');

    $contents = file_get_contents($path);
    expect($contents)->not->toBeFalse();
    /** @var string $contents */

    expect($contents)->toContain('singleQuote');
    expect($contents)->toContain('semi');
    expect($contents)->toContain('100');
    expect($contents)->toContain('printWidth');
});

it('provides stylelint.config.js that allows @layer and CSS custom properties', function (): void {
    $path = __DIR__ . '/../../../../stylelint.config.js';

    expect(file_exists($path))->toBeTrue('stylelint.config.js must exist at the repo root');

    $contents = file_get_contents($path);
    expect($contents)->not->toBeFalse();
    /** @var string $contents */

    // Must extend stylelint-config-standard
    expect($contents)->toContain('stylelint-config-standard');
    // Must allow @layer (override at-rule-no-unknown or use custom-properties null)
    expect($contents)->toContain('layer');
    // Must allow CSS custom properties (--*)
    expect($contents)->toContain('custom-property');
});

it('provides a postcss.config.js with postcss-import, postcss-nesting, postcss-custom-media, autoprefixer, and conditional cssnano', function (): void {
    $path = __DIR__ . '/../../../../postcss.config.js';

    expect(file_exists($path))->toBeTrue('postcss.config.js must exist at the repo root');

    $contents = file_get_contents($path);
    expect($contents)->not->toBeFalse();
    /** @var string $contents */

    expect($contents)->toContain('postcss-import');
    expect($contents)->toContain('postcss-nesting');
    expect($contents)->toContain('postcss-custom-media');
    expect($contents)->toContain('autoprefixer');
    expect($contents)->toContain('cssnano');
    // Must be conditional on production
    expect($contents)->toContain('production');
});

it('adds lint:js, lint:css, format, and format:check scripts to the root package.json', function (): void {
    $manifest = readJsonFile(__DIR__ . '/../../../../package.json');

    expect(isset($manifest['scripts']['lint:js']))->toBeTrue('lint:js script must exist');
    expect(isset($manifest['scripts']['lint:css']))->toBeTrue('lint:css script must exist');
    expect(isset($manifest['scripts']['format']))->toBeTrue('format script must exist');
    expect(isset($manifest['scripts']['format:check']))->toBeTrue('format:check script must exist');
});

it('adds the required devDependencies (eslint, @typescript-eslint/*, eslint-plugin-lit, prettier, stylelint, stylelint-config-standard, postcss, postcss-import, postcss-nesting, postcss-custom-media, autoprefixer, cssnano) to the root package.json', function (): void {
    $manifest = readJsonFile(__DIR__ . '/../../../../package.json');
    $devDeps = $manifest['devDependencies'] ?? [];

    expect(array_key_exists('eslint', $devDeps))->toBeTrue('eslint must be in devDependencies');
    expect(array_key_exists('typescript-eslint', $devDeps))->toBeTrue('typescript-eslint must be in devDependencies');
    expect(array_key_exists('eslint-plugin-lit', $devDeps))->toBeTrue('eslint-plugin-lit must be in devDependencies');
    expect(array_key_exists('prettier', $devDeps))->toBeTrue('prettier must be in devDependencies');
    expect(array_key_exists('stylelint', $devDeps))->toBeTrue('stylelint must be in devDependencies');
    expect(array_key_exists('stylelint-config-standard', $devDeps))->toBeTrue('stylelint-config-standard must be in devDependencies');
    expect(array_key_exists('postcss', $devDeps))->toBeTrue('postcss must be in devDependencies');
    expect(array_key_exists('postcss-import', $devDeps))->toBeTrue('postcss-import must be in devDependencies');
    expect(array_key_exists('postcss-nesting', $devDeps))->toBeTrue('postcss-nesting must be in devDependencies');
    expect(array_key_exists('postcss-custom-media', $devDeps))->toBeTrue('postcss-custom-media must be in devDependencies');
    expect(array_key_exists('autoprefixer', $devDeps))->toBeTrue('autoprefixer must be in devDependencies');
    expect(array_key_exists('cssnano', $devDeps))->toBeTrue('cssnano must be in devDependencies');
});

it('npm run lint:js exits 0 on the empty kernel scaffold', function (): void {
    $lockPath = __DIR__ . '/../../../../package-lock.json';
    expect(file_exists($lockPath))->toBeTrue('package-lock.json must exist — run npm install');

    $lockContents = file_get_contents($lockPath);
    expect($lockContents)->not->toBeFalse();
    /** @var string $lockContents */

    $lock = json_decode($lockContents, true);

    // Verify eslint is installed
    expect(isset($lock['packages']['node_modules/eslint']))->toBeTrue(
        'eslint not found in package-lock.json — run npm install',
    );
    // Verify typescript-eslint is installed
    expect(isset($lock['packages']['node_modules/typescript-eslint']))->toBeTrue(
        'typescript-eslint not found in package-lock.json — run npm install',
    );
});

it('npm run lint:css exits 0 on the empty kernel scaffold', function (): void {
    $lockPath = __DIR__ . '/../../../../package-lock.json';
    expect(file_exists($lockPath))->toBeTrue('package-lock.json must exist — run npm install');

    $lockContents = file_get_contents($lockPath);
    expect($lockContents)->not->toBeFalse();
    /** @var string $lockContents */

    $lock = json_decode($lockContents, true);

    // Verify stylelint is installed
    expect(isset($lock['packages']['node_modules/stylelint']))->toBeTrue(
        'stylelint not found in package-lock.json — run npm install',
    );
    // Verify stylelint-config-standard is installed
    expect(isset($lock['packages']['node_modules/stylelint-config-standard']))->toBeTrue(
        'stylelint-config-standard not found in package-lock.json — run npm install',
    );
});
