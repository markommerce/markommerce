<?php

declare(strict_types=1);

it('asserts no packages/*/resources/js/.generated/.gitignore file exists', function (): void {
    $matches = glob(__DIR__ . '/../../packages/*/resources/js/.generated/.gitignore');

    expect($matches)->toBeEmpty(
        'Found redundant .gitignore files in .generated directories (root .gitignore already excludes them): '
        . implode(', ', $matches ?: [])
    );
});
