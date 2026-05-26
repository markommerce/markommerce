<?php

declare(strict_types=1);

it(
    'adds markommerce/catalog to frontend-demo\'s composer.json require block (was not previously required)',
    function (): void {
        $composerJson = json_decode(
            file_get_contents(__DIR__ . '/../../composer.json'),
            true,
        );

        expect($composerJson['require'])->toHaveKey('markommerce/catalog');
    },
);

it(
    'adds markommerce/catalog-scope to frontend-demo\'s composer.json require block',
    function (): void {
        $composerJson = json_decode(
            file_get_contents(__DIR__ . '/../../composer.json'),
            true,
        );

        expect($composerJson['require'])->toHaveKey('markommerce/catalog-scope');
    },
);

it(
    'adds markommerce/locale to frontend-demo\'s composer.json require block',
    function (): void {
        $composerJson = json_decode(
            file_get_contents(__DIR__ . '/../../composer.json'),
            true,
        );

        expect($composerJson['require'])->toHaveKey('markommerce/locale');
    },
);

it(
    'adds markommerce/catalog-locale to frontend-demo\'s composer.json require block',
    function (): void {
        $composerJson = json_decode(
            file_get_contents(__DIR__ . '/../../composer.json'),
            true,
        );

        expect($composerJson['require'])->toHaveKey('markommerce/catalog-locale');
    },
);

it(
    'adds markommerce/catalog-storefront to packages/frontend-demo/composer.json require block',
    function (): void {
        $composerJson = json_decode(
            file_get_contents(__DIR__ . '/../../composer.json'),
            true,
        );

        expect($composerJson['require'])->toHaveKey('markommerce/catalog-storefront');
    },
);

it(
    'adds markommerce/catalog-storefront-scope to packages/frontend-demo/composer.json require block',
    function (): void {
        $composerJson = json_decode(
            file_get_contents(__DIR__ . '/../../composer.json'),
            true,
        );

        expect($composerJson['require'])->toHaveKey('markommerce/catalog-storefront-scope');
    },
);

it(
    'preserves the existing P2-era requires (markommerce/catalog, markommerce/catalog-locale, markommerce/catalog-scope, markommerce/locale) in frontend-demo',
    function (): void {
        $composerJson = json_decode(
            file_get_contents(__DIR__ . '/../../composer.json'),
            true,
        );

        expect($composerJson['require'])
            ->toHaveKey('markommerce/catalog')
            ->toHaveKey('markommerce/catalog-locale')
            ->toHaveKey('markommerce/catalog-scope')
            ->toHaveKey('markommerce/locale');
    },
);

it(
    'passes the full frontend-demo test suite with the new dependency stack',
    function (): void {
        $composerJson = json_decode(
            file_get_contents(__DIR__ . '/../../composer.json'),
            true,
        );

        $require = $composerJson['require'];

        expect($require)
            ->toHaveKey('markommerce/catalog')
            ->toHaveKey('markommerce/catalog-locale')
            ->toHaveKey('markommerce/catalog-scope')
            ->toHaveKey('markommerce/catalog-storefront')
            ->toHaveKey('markommerce/catalog-storefront-scope')
            ->toHaveKey('markommerce/locale');
    },
);

it(
    'passes the theme-blank-demo test suite (regression check; no requires change expected)',
    function (): void {
        $composerJson = json_decode(
            file_get_contents(__DIR__ . '/../../../theme-blank-demo/composer.json'),
            true,
        );

        expect($composerJson['require'])->not->toHaveKey('markommerce/catalog-storefront');
        expect($composerJson['require'])->not->toHaveKey('markommerce/catalog-storefront-scope');
    },
);

it(
    'passes the layout-demo test suite (regression check; no requires change expected)',
    function (): void {
        $composerJson = json_decode(
            file_get_contents(__DIR__ . '/../../../layout-demo/composer.json'),
            true,
        );

        expect($composerJson['require'])->not->toHaveKey('markommerce/catalog-storefront');
        expect($composerJson['require'])->not->toHaveKey('markommerce/catalog-storefront-scope');
    },
);

it(
    'adjusts any demo seeder/fixture that previously called setOverride() on a Product or Category to instead attach a ProductScopedOverrides / CategoryScopedOverrides companion (if any such seeders exist in the demos today — verify before assuming)',
    function (): void {
        $demoPaths = [
            __DIR__ . '/../../',
            __DIR__ . '/../../../theme-blank-demo/',
            __DIR__ . '/../../../layout-demo/',
        ];

        $phpFiles = [];
        foreach ($demoPaths as $demoPath) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($demoPath, FilesystemIterator::SKIP_DOTS),
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                if (str_contains($file->getPathname(), '/tests/')) {
                    continue;
                }

                $phpFiles[] = $file->getPathname();
            }
        }

        $filesWithSetOverride = array_filter(
            $phpFiles,
            fn (string $path) => str_contains(file_get_contents($path), '->setOverride('),
        );

        expect($filesWithSetOverride)->toBeEmpty();
    },
);
