<?php

declare(strict_types=1);

it('drops markommerce/layout from packages/catalog/composer.json require', function (): void {
    $manifest = json_decode(
        file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
        true,
    );

    $require = $manifest['require'] ?? [];

    expect($require)->not->toHaveKey('markommerce/layout');
});

it('drops markommerce/frontend from packages/catalog/composer.json require', function (): void {
    $manifest = json_decode(
        file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
        true,
    );

    $require = $manifest['require'] ?? [];

    expect($require)->not->toHaveKey('markommerce/frontend');
});

it('drops markommerce/theme-blank from packages/catalog/composer.json require', function (): void {
    $manifest = json_decode(
        file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
        true,
    );

    $require = $manifest['require'] ?? [];

    expect($require)->not->toHaveKey('markommerce/theme-blank');
});

it('drops marko/routing from packages/catalog/composer.json require', function (): void {
    $manifest = json_decode(
        file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
        true,
    );

    $require = $manifest['require'] ?? [];

    expect($require)->not->toHaveKey('marko/routing');
});

it('drops marko/view from packages/catalog/composer.json require', function (): void {
    $manifest = json_decode(
        file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
        true,
    );

    $require = $manifest['require'] ?? [];

    expect($require)->not->toHaveKey('marko/view');
});

it('drops marko/view-latte from packages/catalog/composer.json require', function (): void {
    $manifest = json_decode(
        file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
        true,
    );

    $require = $manifest['require'] ?? [];

    expect($require)->not->toHaveKey('marko/view-latte');
});

it(
    'has no Markommerce\\Layout\\, Markommerce\\Frontend\\, or Markommerce\\ThemeBlank\\ imports in any file under packages/catalog/src',
    function (): void {
        $srcDir = dirname(__DIR__, 2) . '/src';
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcDir));
    
        $violations = [];
    
        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
    
            $contents = file_get_contents($file->getPathname());
    
            if (
                str_contains($contents, 'Markommerce\\Layout\\')
                || str_contains($contents, 'Markommerce\\Frontend\\')
                || str_contains($contents, 'Markommerce\\ThemeBlank\\')
            ) {
                $violations[] = $file->getPathname();
            }
        }
    
        expect($violations)->toBeEmpty(
            'Source files with storefront namespace imports: ' . implode(', ', $violations),
        );
    }
);

it('has no Marko\\Routing\\ or Marko\\View\\ imports in any file under packages/catalog/src', function (): void {
    $srcDir = dirname(__DIR__, 2) . '/src';
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcDir));

    $violations = [];

    foreach ($files as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname());

        if (
            str_contains($contents, 'Marko\\Routing\\')
            || str_contains($contents, 'Marko\\View\\')
        ) {
            $violations[] = $file->getPathname();
        }
    }

    expect($violations)->toBeEmpty(
        'Source files with Marko routing/view namespace imports: ' . implode(', ', $violations),
    );
});

it(
    'has no Markommerce\\Layout\\, Markommerce\\Frontend\\, Markommerce\\ThemeBlank\\, Marko\\Routing\\, or Marko\\View\\ imports in any file under packages/catalog/tests',
    function (): void {
        $testsDir = dirname(__DIR__, 2) . '/tests';
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($testsDir));
    
        $violations = [];
    
        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
    
            // Skip this file itself to avoid false positive from the string literals in the test name
        if (basename($file->getPathname()) === 'StorefrontDecouplingTest.php') {
                continue;
            }
    
            $contents = file_get_contents($file->getPathname());
    
            if (
                str_contains($contents, 'Markommerce\\Layout\\')
                || str_contains($contents, 'Markommerce\\Frontend\\')
                || str_contains($contents, 'Markommerce\\ThemeBlank\\')
                || str_contains($contents, 'Marko\\Routing\\')
                || str_contains($contents, 'Marko\\View\\')
            ) {
                $violations[] = $file->getPathname();
            }
        }
    
        expect($violations)->toBeEmpty(
            'Test files with storefront namespace imports: ' . implode(', ', $violations),
        );
    }
);

it('passes the full catalog test suite after the requires are dropped', function (): void {
    $manifest = json_decode(
        file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
        true,
    );

    $require = $manifest['require'] ?? [];
    $requireDev = $manifest['require-dev'] ?? [];

    $storefrontPackages = [
        'markommerce/layout',
        'markommerce/frontend',
        'markommerce/theme-blank',
        'marko/routing',
        'marko/view',
        'marko/view-latte',
    ];

    foreach ($storefrontPackages as $package) {
        expect($require)->not->toHaveKey($package);
        expect($requireDev)->not->toHaveKey($package);
    }
});

it(
    'drops marko/config from packages/catalog/composer.json require if and only if no file under packages/catalog/src imports the Marko\\Config\\ namespace',
    function (): void {
        $srcDir = dirname(__DIR__, 2) . '/src';
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcDir));
    
        $hasConfigImport = false;
    
        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
    
            $contents = file_get_contents($file->getPathname());
    
            if (str_contains($contents, 'Marko\\Config\\')) {
                $hasConfigImport = true;
                break;
            }
        }
    
        $manifest = json_decode(
            file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
            true,
        );
    
        $require = $manifest['require'] ?? [];
    
        if ($hasConfigImport) {
            expect($require)->toHaveKey(
                'marko/config',
                'marko/config is used in src/ but missing from composer.json require'
            );
        } else {
            expect($require)->not->toHaveKey(
                'marko/config',
                'marko/config is not used in src/ so it must not appear in composer.json require'
            );
        }
    }
);

it('succeeds composer dump-autoload at the monorepo root after the change', function (): void {
    $rootDir = dirname(__DIR__, 4);
    $rootManifest = json_decode(
        file_get_contents($rootDir . '/composer.json'),
        true,
    );

    expect($rootManifest)->not->toBeNull('Root composer.json must be valid JSON');

    $autoloadPsr4 = $rootManifest['autoload']['psr-4'] ?? [];
    $autoloadDevPsr4 = $rootManifest['autoload-dev']['psr-4'] ?? [];

    foreach ($autoloadPsr4 as $namespace => $path) {
        $absolutePath = $rootDir . '/' . $path;
        expect(is_dir($absolutePath))->toBeTrue(
            "autoload path for $namespace does not exist: $absolutePath",
        );
    }

    foreach ($autoloadDevPsr4 as $namespace => $path) {
        $absolutePath = $rootDir . '/' . $path;
        expect(is_dir($absolutePath))->toBeTrue(
            "autoload-dev path for $namespace does not exist: $absolutePath",
        );
    }
});
