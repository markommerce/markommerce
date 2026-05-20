<?php

declare(strict_types=1);
use Markommerce\Scope\Attributes\Scoped;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\NoDriverException;
use Markommerce\Scope\Exceptions\ScopeConfigurationException;
use Markommerce\Scope\Exceptions\ScopeContextException;
use Markommerce\Scope\Exceptions\ScopeStorageException;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Exceptions\UnknownScopeException;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Metadata\ScopeMetadata;
use Markommerce\Scope\Metadata\ScopeMetadataFactory;
use Markommerce\Scope\Query\ScopedFieldExpression;
use Markommerce\Scope\Query\ScopedFieldRendererInterface;
use Markommerce\Scope\Query\ScopedOrderBy;
use Markommerce\Scope\Query\ScopedOrderByFactory;
use Markommerce\Scope\Registry\PhpScopeRegistry;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Resolution\ScopeWalker;
use Markommerce\Scope\Resolution\ScopeWalkResult;
use Markommerce\Scope\Resolver\ScopeResolver;
use Markommerce\Scope\Storage\HasScopesInterface;
use Markommerce\Scope\Storage\ScopedDataSerializer;
use Markommerce\Scope\Validation\ScopedEntityValidator;

it('autoloads every Markommerce\\Scope\\ class without a fatal error', function (): void {
    $classes = [
        Scoped::class,
        ScopeAxis::class,
        ScopeContext::class,
        NoDriverException::class,
        ScopeConfigurationException::class,
        ScopeContextException::class,
        ScopeStorageException::class,
        UnknownAxisException::class,
        UnknownScopeException::class,
        ScopeHierarchy::class,
        ScopeMetadata::class,
        ScopeMetadataFactory::class,
        ScopedOrderBy::class,
        ScopedOrderByFactory::class,
        ScopedFieldExpression::class,
        ScopedFieldRendererInterface::class,
        PhpScopeRegistry::class,
        ScopeRegistryInterface::class,
        ScopeWalker::class,
        ScopeWalkResult::class,
        ScopeResolver::class,
        HasScopesInterface::class,
        ScopedDataSerializer::class,
        ScopedEntityValidator::class,
    ];

    foreach ($classes as $fqcn) {
        expect(class_exists($fqcn) || interface_exists($fqcn) || trait_exists($fqcn))
            ->toBeTrue("Class/interface/trait $fqcn could not be autoloaded");
    }
});

it('has no remaining upstream-namespace references in packages/scope/src/', function (): void {
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

it('preserves all Marko\\Config\\, Marko\\Core\\, Marko\\Database\\ imports unchanged', function (): void {
    $upstreamSrcDir = '/workspace/marko/packages/scope/src';
    $localSrcDir = dirname(__DIR__, 3) . '/src';

    if (!is_dir($upstreamSrcDir)) {
        test()->markTestSkipped('Upstream marko/packages/scope/src not available — skipping comparison.');
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

    $configPattern = '/use Marko\\\\Config\\\\/';
    $corePattern = '/use Marko\\\\Core\\\\/';
    $databasePattern = '/use Marko\\\\Database\\\\/';

    expect($countImports($localSrcDir, $configPattern))
        ->toBe($countImports($upstreamSrcDir, $configPattern));
    expect($countImports($localSrcDir, $corePattern))
        ->toBe($countImports($upstreamSrcDir, $corePattern));
    expect($countImports($localSrcDir, $databasePattern))
        ->toBe($countImports($upstreamSrcDir, $databasePattern));
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

it('does not introduce a final class in any src file', function (): void {
    $srcDir = dirname(__DIR__, 3) . '/src';
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($srcDir),
    );

    $finalClasses = [];
    foreach ($files as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname());

        if (preg_match('/\bfinal\s+(class|readonly\s+class)\b/', $contents)) {
            $finalClasses[] = $file->getPathname();
        }
    }

    expect($finalClasses)->toBe([]);
});

it(
    'NoDriverException::DRIVER_PACKAGES contains markommerce/scope-pgsql and does not reference the old upstream package names',
    function (): void {
        $reflection = new ReflectionClass(NoDriverException::class);
        $constant = $reflection->getReflectionConstant('DRIVER_PACKAGES');

        expect($constant)->not->toBeFalse();

        $packages = $constant->getValue();

        // The new package name must be present
        expect($packages)->toContain('markommerce/scope-pgsql');

        // No entry should start with the old vendor prefix "marko/"
        $oldVendorPackages = array_filter(
            $packages,
            static fn (string $p) => str_starts_with($p, 'marko/'),
        );
        expect($oldVendorPackages)->toBe([]);
    },
);

it('NoDriverException::noDriverInstalled()->getSuggestion() mentions markommerce/scope-pgsql', function (): void {
    $exception = NoDriverException::noDriverInstalled();

    expect($exception->getSuggestion())->toContain('markommerce/scope-pgsql');
});

it('the Scope class file no longer exists in packages/scope/src', function (): void {
    $scopeFile = dirname(__DIR__, 3) . '/src/Scope.php';

    expect(file_exists($scopeFile))->toBeFalse('packages/scope/src/Scope.php should have been deleted');
});

it('no PHP file under packages/scope or packages/scope-pgsql imports Markommerce\Scope\Scope', function (): void {
    $packagesRoot = dirname(__DIR__, 4);
    $dirsToCheck = [
        $packagesRoot . '/scope/src',
        $packagesRoot . '/scope/tests',
        $packagesRoot . '/scope-pgsql/src',
        $packagesRoot . '/scope-pgsql/tests',
    ];

    $thisFile = __FILE__;
    $violations = [];
    foreach ($dirsToCheck as $dir) {
        if (!is_dir($dir)) {
            continue;
        }

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            if ($file->getRealPath() === realpath($thisFile)) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            if (preg_match('/use Markommerce\\\\Scope\\\\Scope;/', $contents)) {
                $violations[] = $file->getPathname();
            }
        }
    }

    expect($violations)->toBe([], 'Files still importing the deleted Scope class: ' . implode(', ', $violations));
});

it('no documentation file under docs imports Markommerce\Scope\Scope in a code block', function (): void {
    $docsRoot = dirname(__DIR__, 5) . '/docs';

    if (!is_dir($docsRoot)) {
        test()->markTestSkipped('docs/ directory not available — skipping.');
    }

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($docsRoot));
    $violations = [];

    foreach ($files as $file) {
        if (!$file->isFile()) {
            continue;
        }

        $ext = $file->getExtension();
        if ($ext !== 'md' && $ext !== 'mdx') {
            continue;
        }

        $contents = file_get_contents($file->getPathname());
        if (preg_match('/use Markommerce\\\\Scope\\\\Scope;/', $contents)) {
            $violations[] = $file->getPathname();
        }
    }

    expect($violations)->toBe(
        [],
        'These doc files still contain a Scope import: ' . implode(', ', $violations),
    );
});
