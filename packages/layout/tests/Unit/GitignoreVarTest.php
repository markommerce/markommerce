<?php

declare(strict_types=1);

it('adds the var directory to the repo .gitignore', function (): void {
    $path = __DIR__ . '/../../../../.gitignore';

    expect(file_exists($path))->toBeTrue();

    $contents = file_get_contents($path);
    expect($contents)->not->toBeFalse();

    /** @var string $contents */
    $lines = array_map('trim', explode("\n", $contents));

    expect($lines)->toContain('var/');
});
