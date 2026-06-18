<?php

declare(strict_types=1);

it(
    'it no longer references markommerce/config-scope-pgsql anywhere in sources, tests, or composer',
    function (): void {
        $root = dirname(__DIR__, 5);

        // Scan all PHP, JSON source files, excluding:
        // - vendor/ (third-party code)
        // - .claude/plans/ (historical plan documents)
        // - docs/ (docs site pages — updated in a later task)
        // - .phpstan-cache/ (generated cache files)
        // - FEATURES.md (updated in a later task)
        // - tests/Unit/Docs/ (documentation contract tests updated in a later task)
        // - tests/Unit/FeaturesDocTest.php (updated in a later task)
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        );

        $violations = [];

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            $path = $file->getPathname();

            // Skip generated/deferred/external files
            if (str_contains($path, '/vendor/')
                || str_contains($path, '/.claude/plans/')
                || str_contains($path, '/docs/')
                || str_contains($path, '/.phpstan-cache/')
                || str_ends_with($path, 'composer.lock')
                || str_ends_with($path, 'FEATURES.md')
                || str_contains($path, '/tests/Unit/Docs/')
                || str_contains($path, 'FeaturesDocTest.php')
                || !in_array($file->getExtension(), ['php', 'json'], true)
            ) {
                continue;
            }

            // Skip this test file itself (it references the package name by definition)
            if (str_ends_with($path, 'SourceTreeTest.php')) {
                continue;
            }

            // Skip ComposerManifestTest: it keeps a "config does NOT require config-scope-pgsql"
            // assertion that is trivially true and explicitly retained per task spec
            if (str_ends_with($path, 'ComposerManifestTest.php')) {
                continue;
            }

            // Skip StoreProfileTest: it holds intentional absence-assertions (not->toContain)
            // for all four retired -pgsql drivers including config-scope-pgsql
            if (str_ends_with($path, 'StoreProfileTest.php')) {
                continue;
            }

            // Skip .claude/ workspace config files (local permission lists, not source code)
            if (str_contains($path, '/.claude/')) {
                continue;
            }

            $content = (string) file_get_contents($path);

            if (str_contains($content, 'config-scope-pgsql')) {
                $violations[] = str_replace($root . '/', '', $path);
            }
        }

        expect($violations)->toBe([]);
    },
);
