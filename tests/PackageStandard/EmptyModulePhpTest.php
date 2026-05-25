<?php

declare(strict_types=1);

it('asserts that if a packages/*/module.php exists, the file returns a non-empty array', function (): void {
    $packagesDir = __DIR__ . '/../../packages';
    $moduleFiles = glob($packagesDir . '/*/module.php') ?: [];

    expect($moduleFiles)->not->toBeEmpty();

    foreach ($moduleFiles as $moduleFile) {
        $result = require $moduleFile;

        expect($result)
            ->not->toBe([], basename(dirname($moduleFile)) . '/module.php must not return an empty array — delete it or add bindings');
    }
});
