<?php

declare(strict_types=1);

it('it has a README', function (): void {
    $path = __DIR__ . '/../../README.md';

    expect(file_exists($path))->toBeTrue();

    $contents = file_get_contents($path);
    expect($contents)->not->toBeFalse();
    expect($contents)->toContain('markommerce/layout-demo');
});
