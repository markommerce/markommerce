<?php

declare(strict_types=1);

use Markommerce\Scope\Exceptions\NoDriverException;
use Markommerce\Scope\Query\ScopedFieldExpression;
use Markommerce\Scope\Signature\ScopeSignature;

it('the old ScopeSortExpression class file does not exist', function (): void {
    expect(file_exists(dirname(__DIR__, 3) . '/src/Query/ScopeSortExpression.php'))->toBeFalse();
});

it('the old ScopeSortRendererInterface file does not exist', function (): void {
    expect(file_exists(dirname(__DIR__, 3) . '/src/Query/ScopeSortRendererInterface.php'))->toBeFalse();
});

it(
    'no PHP file under packages/scope or packages/scope-pgsql imports ScopeSortRendererInterface or ScopeSortExpression',
    function (): void {
        $packagesDir = dirname(__DIR__, 5);
        $dirs = [
            $packagesDir . '/scope',
            $packagesDir . '/scope-pgsql',
        ];
    
        $matches = [];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
    
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    
            foreach ($files as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }
    
                $contents = file_get_contents($file->getPathname());
    
                if (str_contains($contents, 'ScopeSortRendererInterface') || str_contains(
                    $contents,
                    'ScopeSortExpression'
                )) {
                    $matches[] = $file->getPathname();
                }
            }
        }
    
        expect($matches)->toBe([]);
    }
);

it('ScopedFieldExpression does NOT have a direction field', function (): void {
    $reflection = new ReflectionClass(ScopedFieldExpression::class);

    expect($reflection->hasProperty('direction'))->toBeFalse();
});

it('ScopedFieldExpression defaults jsonColumn to "scopes"', function (): void {
    $expression = new ScopedFieldExpression(
        property: 'price',
        column: 'price',
        candidateSignatures: [],
    );

    expect($expression->jsonColumn)->toBe('scopes');
});

it('ScopedFieldExpression accepts an empty signatures list (fallback-column-only case)', function (): void {
    $expression = new ScopedFieldExpression(
        property: 'price',
        column: 'price',
        candidateSignatures: [],
    );

    expect($expression->candidateSignatures)->toBe([]);
});

it(
    'NoDriverException::noDriverInstalled() message and context reference ScopedFieldRendererInterface (not the old ScopeSortRendererInterface vocabulary)',
    function (): void {
        $exception = NoDriverException::noDriverInstalled();
    
        expect($exception->getMessage())->not->toContain('scope sort renderer')
            ->and($exception->getMessage())->not->toContain('ScopeSortRendererInterface')
            ->and($exception->getContext())->not->toContain('ScopeSortRendererInterface')
            ->and($exception->getContext())->toContain('ScopedFieldRendererInterface');
    }
);

it(
    'ScopedFieldExpression preserves the candidateSignatures order as given (the constructor does not sort or normalize them; the order is the renderer\'s contract)',
    function (): void {
        $sig1 = new ScopeSignature(['store' => 'en.gb']);
        $sig2 = new ScopeSignature(['store' => 'en']);
        $sig3 = new ScopeSignature(['channel' => 'b2b']);
    
        $expression = new ScopedFieldExpression(
            property: 'price',
            column: 'price',
            candidateSignatures: [$sig1, $sig2, $sig3],
        );
    
        expect($expression->candidateSignatures[0])->toBe($sig1)
            ->and($expression->candidateSignatures[1])->toBe($sig2)
            ->and($expression->candidateSignatures[2])->toBe($sig3);
    }
);

it('ScopedFieldExpression can be constructed with property, column, and a list of signatures', function (): void {
    $signatures = [
        new ScopeSignature(['store' => 'en.gb']),
        new ScopeSignature(['store' => 'en']),
    ];

    $expression = new ScopedFieldExpression(
        property: 'price',
        column: 'price',
        candidateSignatures: $signatures,
    );

    expect($expression->property)->toBe('price')
        ->and($expression->column)->toBe('price')
        ->and($expression->candidateSignatures)->toBe($signatures);
});
