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
