<?php

declare(strict_types=1);

/**
 * @return array<string, mixed>
 */
function readLayoutManifest(): array
{
    $path = __DIR__ . '/../../composer.json';
    $contents = file_get_contents($path);
    expect($contents)->not->toBeFalse();
    /** @var string $contents */

    return json_decode($contents, true);
}

it('has a composer.json declaring the markommerce/layout package', function (): void {
    $path = __DIR__ . '/../../composer.json';

    expect(file_exists($path))->toBeTrue();

    $manifest = readLayoutManifest();

    expect($manifest['name'])->toBe('markommerce/layout');
});

it('registers as a marko module via the extra.marko.module flag', function (): void {
    $manifest = readLayoutManifest();

    expect($manifest['extra']['marko']['module'])->toBeTrue();
});

it('autoloads the Markommerce\Layout namespace from src', function (): void {
    $manifest = readLayoutManifest();

    expect($manifest['autoload']['psr-4']['Markommerce\\Layout\\'])->toBe('src/');
});

it('autoloads the Markommerce\Layout\Tests namespace from tests', function (): void {
    $manifest = readLayoutManifest();

    expect($manifest['autoload-dev']['psr-4']['Markommerce\\Layout\\Tests\\'])->toBe('tests/');
});

it('ships a module.php that returns an array', function (): void {
    $path = __DIR__ . '/../../module.php';

    expect(file_exists($path))->toBeTrue();

    $result = require $path;

    expect($result)->toBeArray();
});

it('declares marko/core, marko/routing, marko/view and marko/cli as dependencies', function (): void {
    $manifest = readLayoutManifest();

    expect($manifest['require'])->toHaveKey('marko/core');
    expect($manifest['require']['marko/core'])->toBe('self.version');
    expect($manifest['require'])->toHaveKey('marko/routing');
    expect($manifest['require']['marko/routing'])->toBe('self.version');
    expect($manifest['require'])->toHaveKey('marko/view');
    expect($manifest['require']['marko/view'])->toBe('self.version');
    expect($manifest['require'])->toHaveKey('marko/cli');
    expect($manifest['require']['marko/cli'])->toBe('self.version');
});

it('adds the var directory to the repo .gitignore', function (): void {
    $path = __DIR__ . '/../../../../.gitignore';

    expect(file_exists($path))->toBeTrue();

    $contents = file_get_contents($path);
    expect($contents)->not->toBeFalse();

    /** @var string $contents */
    $lines = array_map('trim', explode("\n", $contents));

    expect($lines)->toContain('var/');
});
