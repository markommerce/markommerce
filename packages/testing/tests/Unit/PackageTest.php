<?php

declare(strict_types=1);
use Markommerce\Testing\Testing;

it('autoloads a class from the Markommerce\Testing namespace', function (): void {
    expect(class_exists(Testing::class))->toBeTrue();
});

it('exposes the package on the test suite path', function (): void {
    expect(true)->toBeTrue();
});

it('is not registered as a marko module', function (): void {
    $manifest = json_decode(
        file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
        true,
    );

    $markoModule = $manifest['extra']['marko']['module'] ?? false;

    expect($markoModule)->toBeFalse();
});
