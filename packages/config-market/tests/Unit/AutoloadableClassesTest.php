<?php

declare(strict_types=1);

it('has no src classes to autoload yet (placeholder package with empty src/)', function (): void {
    $srcPath = dirname(__DIR__, 2) . '/src';

    $phpFiles = array_filter(
        glob($srcPath . '/**/*.php') ?: [],
        fn (string $f) => !str_ends_with($f, '.gitkeep'),
    );

    expect($phpFiles)->toBeEmpty();
});
