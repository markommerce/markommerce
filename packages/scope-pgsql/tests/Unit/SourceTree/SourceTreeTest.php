<?php

declare(strict_types=1);
use Markommerce\Scope\PgSql\Query\PgSqlScopedFieldRenderer;
use Markommerce\Scope\Query\ScopedFieldRendererInterface;

it('autoloads Markommerce\\Scope\\PgSql\\Query\\PgSqlScopedFieldRenderer without a fatal error', function (): void {
    expect(class_exists(PgSqlScopedFieldRenderer::class))
        ->toBeTrue('Class Markommerce\\Scope\\PgSql\\Query\\PgSqlScopedFieldRenderer could not be autoloaded');
});

it('has no remaining upstream-namespace references in packages/scope-pgsql/src/', function (): void {
    $srcDir = dirname(__DIR__, 3) . '/src';
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($srcDir),
    );

    $matches = [];
    foreach ($files as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname());

        // Detect the old upstream namespace (single-backslash form, excluding Markommerce prefix)
        if (preg_match('/(?<!Markommerc)e\\\\Scope\\\\/', $contents)) {
            $matches[] = $file->getPathname() . ' (single-backslash old-namespace)';
        }

        // Detect the old upstream namespace (double-backslash form in string literals)
        if (preg_match('/Marko\\\\\\\\Scope\\\\\\\\/', $contents)) {
            $matches[] = $file->getPathname() . ' (double-backslash old-namespace)';
        }
    }

    expect($matches)->toBe([]);
});

it('preserves Marko\\Database\\, Marko\\Database\\PgSql\\, Marko\\Core\\ imports unchanged', function (): void {
    $upstreamSrcDir = '/workspace/marko/packages/scope-pgsql/src';
    $localSrcDir = dirname(__DIR__, 3) . '/src';

    if (!is_dir($upstreamSrcDir)) {
        test()->markTestSkipped('Upstream marko/packages/scope-pgsql/src not available — skipping comparison.');
    }

    $countImports = function (string $dir, string $pattern): int {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir),
        );
        $count = 0;
        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $contents = file_get_contents($file->getPathname());
            preg_match_all($pattern, $contents, $m);
            $count += count($m[0]);
        }

        return $count;
    };

    $databasePattern = '/use Marko\\\\Database\\\\/';
    $databasePgsqlPattern = '/use Marko\\\\Database\\\\PgSql\\\\/';
    $corePattern = '/use Marko\\\\Core\\\\/';

    expect($countImports($localSrcDir, $databasePattern))
        ->toBe($countImports($upstreamSrcDir, $databasePattern));
    expect($countImports($localSrcDir, $databasePgsqlPattern))
        ->toBe($countImports($upstreamSrcDir, $databasePgsqlPattern));
    expect($countImports($localSrcDir, $corePattern))
        ->toBe($countImports($upstreamSrcDir, $corePattern));
});

it('keeps declare(strict_types=1) at the top of every src file', function (): void {
    $srcDir = dirname(__DIR__, 3) . '/src';
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($srcDir),
    );

    $missing = [];
    foreach ($files as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname());

        if (!str_contains($contents, 'declare(strict_types=1);')) {
            $missing[] = $file->getPathname();
        }
    }

    expect($missing)->toBe([]);
});

it('PgSqlScopedFieldRenderer implements Markommerce\\Scope\\Query\\ScopedFieldRendererInterface', function (): void {
    $reflection = new ReflectionClass(PgSqlScopedFieldRenderer::class);

    expect($reflection->implementsInterface(ScopedFieldRendererInterface::class))
        ->toBeTrue();
});
