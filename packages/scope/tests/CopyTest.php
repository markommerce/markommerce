<?php

declare(strict_types=1);

it('copies every Unit test file from marko/scope/tests/Unit/ into packages/scope/tests/Unit/', function (): void {
    $testsDir = __DIR__ . '/Unit';

    $expectedFiles = [
        'ScopeAxisTest.php',
        'ModulePhpTest.php',
        'Attributes/ScopedTest.php',
        'Validation/ScopedEntityValidatorTest.php',
        'Resolver/ScopeResolverTest.php',
        'Registry/PhpScopeRegistryTest.php',
        'Registry/ScopeRegistryInterfaceTest.php',
        'Storage/HasScopesTraitTest.php',
        'Storage/ScopedDataSerializerTest.php',
        'Hierarchy/ScopeHierarchyTest.php',
        'Context/ScopeContextTest.php',
        'Resolution/ScopeWalkerTest.php',
        'Query/ScopedOrderByTest.php',
        'Query/ScopedFieldRendererInterfaceTest.php',
        'Query/ScopedFieldExpressionTest.php',
        'Query/ScopedOrderByFactoryTest.php',
        'Metadata/ScopeMetadataFactoryTest.php',
        'Exceptions/ScopeExceptionsTest.php',
        'ScopedOverridesPersistenceTest.php',
        'DefaultScopeResolutionTest.php',
    ];

    foreach ($expectedFiles as $file) {
        expect(file_exists($testsDir . '/' . $file))->toBeTrue("Missing: Unit/$file");
    }
});

it(
    'copies every Feature test file from marko/scope/tests/Feature/ into packages/scope/tests/Feature/',
    function (): void {
        $testsDir = __DIR__ . '/Feature';

        $expectedFiles = [
            'BridgeContributionTest.php',
        ];

        foreach ($expectedFiles as $file) {
            expect(file_exists($testsDir . '/' . $file))->toBeTrue("Missing: Feature/$file");
        }
    },
);

it('has no remaining upstream-namespace references in packages/scope/tests/', function (): void {
    $testsDir = __DIR__;

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($testsDir),
    );

    $matches = [];
    foreach ($files as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        // Skip SourceTreeTest — it may have grep assertions about marko
        if (str_contains($file->getPathname(), 'SourceTree')) {
            continue;
        }

        // Skip this meta-test file itself
        if (str_contains($file->getPathname(), 'CopyTest.php')) {
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
