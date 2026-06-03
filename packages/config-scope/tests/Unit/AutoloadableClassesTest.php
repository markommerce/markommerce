<?php

declare(strict_types=1);

it('autoloads all classes under packages/config-scope/src via PSR-4', function (): void {
    $srcDir = dirname(__DIR__, 2) . '/src';

    expect(is_dir(dirname(__DIR__, 2)))->toBeTrue('Package directory must exist');

    if (!is_dir($srcDir)) {
        // No src/ directory yet — placeholder skeleton, nothing to autoload.
        return;
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcDir));
    $phpFiles = array_filter(
        iterator_to_array($iterator),
        static fn (SplFileInfo $file): bool => $file->isFile() && $file->getExtension() === 'php',
    );

    foreach ($phpFiles as $file) {
        $relative = str_replace($srcDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
        $class = 'Markommerce\\ConfigScope\\' . str_replace(
            [DIRECTORY_SEPARATOR, '.php'],
            ['\\', ''],
            $relative,
        );

        expect(class_exists($class) || interface_exists($class) || enum_exists($class))
            ->toBeTrue("Class or interface $class should be autoloadable under Markommerce\\ConfigScope\\ namespace");
    }
});
