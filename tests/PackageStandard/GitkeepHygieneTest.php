<?php

declare(strict_types=1);

it('asserts the listed obsolete per-package scaffold tests no longer exist', function (): void {
    $obsoleteFiles = [
        'packages/theme-blank/tests/Unit/ComposerManifestTest.php',
        'packages/frontend-demo/tests/Unit/ComposerManifestTest.php',
        'packages/theme-blank-demo/tests/Unit/ComposerManifestTest.php',
        'packages/layout-demo/tests/Unit/ComposerManifestTest.php',
        'packages/frontend/tests/Unit/ComposerManifestTest.php',
        'packages/layout/tests/Unit/PackageScaffoldingTest.php',
        'packages/catalog/tests/Unit/PackageScaffoldingTest.php',
    ];

    $repoRoot = __DIR__ . '/../../';

    foreach ($obsoleteFiles as $obsoleteFile) {
        expect(file_exists($repoRoot . $obsoleteFile))->toBeFalse(
            "Obsolete test file still exists: {$obsoleteFile}",
        );
    }
});

it('asserts the previously-stale .gitkeep files have been removed', function (): void {
    $staleGitkeeps = [
        'packages/frontend-demo/resources/views/.gitkeep',
        'packages/frontend-demo/tests/Unit/.gitkeep',
        'packages/frontend/resources/css/.gitkeep',
        'packages/layout/src/Cache/.gitkeep',
        'packages/layout/src/Command/.gitkeep',
        'packages/layout/src/Compiler/.gitkeep',
        'packages/layout/src/Contracts/.gitkeep',
        'packages/layout/src/Discovery/.gitkeep',
        'packages/layout/src/Exception/.gitkeep',
        'packages/layout/src/Middleware/.gitkeep',
        'packages/layout/src/Operation/.gitkeep',
        'packages/layout/src/Runtime/.gitkeep',
        'packages/layout/src/Source/.gitkeep',
        'packages/layout/tests/Feature/.gitkeep',
        'packages/layout/tests/Unit/.gitkeep',
        'packages/theme-blank-demo/resources/views/.gitkeep',
        'packages/theme-blank/resources/css/.gitkeep',
        'packages/theme-blank/resources/views/layout/.gitkeep',
        'packages/theme-blank/tests/Browser/.gitkeep',
        'packages/theme-blank/tests/Feature/.gitkeep',
    ];

    $repoRoot = __DIR__ . '/../../';

    foreach ($staleGitkeeps as $staleGitkeep) {
        expect(file_exists($repoRoot . $staleGitkeep))->toBeFalse(
            "Stale .gitkeep was not removed: {$staleGitkeep}",
        );
    }
});

it('asserts no .gitkeep file under packages/ is a sibling of another tracked file', function (): void {
    $output = shell_exec('git ls-files packages/');
    expect($output)->not->toBeNull();

    /** @var string $output */
    $trackedFiles = array_filter(
        array_map('trim', explode("\n", $output)),
        static fn (string $line): bool => $line !== '',
    );

    $gitkeepDirs = array_unique(
        array_map(
            static fn (string $file): string => dirname($file),
            array_filter(
                $trackedFiles,
                static fn (string $file): bool => basename($file) === '.gitkeep',
            ),
        ),
    );

    $stale = [];

    foreach ($gitkeepDirs as $dir) {
        $siblings = array_filter(
            $trackedFiles,
            static fn (string $file): bool => dirname($file) === $dir && basename($file) !== '.gitkeep',
        );

        if (count($siblings) > 0) {
            $stale[] = $dir . '/.gitkeep';
        }
    }

    expect($stale)->toBeEmpty(
        'Found stale .gitkeep files (siblings of tracked content): ' . implode(', ', $stale),
    );
});
