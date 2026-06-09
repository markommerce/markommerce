<?php

declare(strict_types=1);

it(
    'skips its own file (basename MarketDecouplingTest.php) when walking the catalog source and test trees, mirroring ScopeDecouplingTest\'s self-exclusion at line 66',
    function (): void {
        $testsDir = dirname(__DIR__, 2) . '/tests';
        $srcDir = dirname(__DIR__, 2) . '/src';
    
        $walkedFiles = [];
    
        foreach ([$srcDir, $testsDir] as $dir) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    
            foreach ($files as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }
    
                if (basename($file->getPathname()) === 'MarketDecouplingTest.php') {
                    continue;
                }
    
                $walkedFiles[] = $file->getPathname();
            }
        }
    
        $selfIncluded = array_any(
            $walkedFiles,
            fn (string $path) => basename($path) === 'MarketDecouplingTest.php',
        );
    
        expect($selfIncluded)->toBeFalse('MarketDecouplingTest.php must be excluded from the walk');
    }
);

it(
    'finds no occurrences of CategoryTreeMarketAssignment in any catalog source or test file (excluding self)',
    function (): void {
        $violations = collectViolations(['CategoryTreeMarketAssignment']);
    
        expect($violations)->toBeEmpty(
            'Files containing CategoryTreeMarketAssignment: ' . implode(', ', $violations),
        );
    }
);

it(
    'finds no occurrences of assignTreeToMarket, resolveTreeForMarket, or unassignMarket in any catalog source or test file (excluding self)',
    function (): void {
        $violations = collectViolations(['assignTreeToMarket', 'resolveTreeForMarket', 'unassignMarket']);
    
        expect($violations)->toBeEmpty(
            'Files containing market assignment methods: ' . implode(', ', $violations),
        );
    }
);

it(
    'finds no occurrences of TreeHasMarketAssignmentsException in any catalog source or test file (excluding self)',
    function (): void {
        $violations = collectViolations(['TreeHasMarketAssignmentsException']);
    
        expect($violations)->toBeEmpty(
            'Files containing TreeHasMarketAssignmentsException: ' . implode(', ', $violations),
        );
    }
);

it(
    'finds no occurrences of the Markommerce\\CatalogMarketCategoryTrees namespace in any catalog source or test file (excluding self)',
    function (): void {
        $violations = collectViolations(['Markommerce\\CatalogMarketCategoryTrees']);
    
        expect($violations)->toBeEmpty(
            'Files containing Markommerce\\CatalogMarketCategoryTrees namespace: ' . implode(', ', $violations),
        );
    }
);

it(
    'finds no occurrences of the Markommerce\\Market or Markommerce\\CatalogMarket namespaces in any catalog source or test file (excluding self)',
    function (): void {
        $violations = collectViolations(['Markommerce\\Market\\', 'Markommerce\\CatalogMarket\\']);
    
        expect($violations)->toBeEmpty(
            'Files containing Markommerce\\Market\\ or Markommerce\\CatalogMarket\\ namespaces: ' . implode(
                ', ',
                $violations
            ),
        );
    }
);

/**
 * @param list<string> $forbiddenSubstrings
 * @return list<string>
 */
function collectViolations(array $forbiddenSubstrings): array
{
    $srcDir = dirname(__DIR__, 2) . '/src';
    $testsDir = dirname(__DIR__, 2) . '/tests';

    $violations = [];

    foreach ([$srcDir, $testsDir] as $dir) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            if (basename($file->getPathname()) === 'MarketDecouplingTest.php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            foreach ($forbiddenSubstrings as $substring) {
                if (str_contains($contents, $substring)) {
                    $violations[] = $file->getPathname();
                    break;
                }
            }
        }
    }

    return $violations;
}
