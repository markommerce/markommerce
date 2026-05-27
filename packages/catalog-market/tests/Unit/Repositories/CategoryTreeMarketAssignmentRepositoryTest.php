<?php

declare(strict_types=1);

use Markommerce\CatalogMarket\Contracts\CategoryTreeMarketAssignmentRepositoryInterface;
use Markommerce\CatalogMarket\Repositories\CategoryTreeMarketAssignmentRepository;

it('CategoryTreeMarketAssignmentRepository lives in Markommerce\\CatalogMarket\\Repositories namespace and preserves the INSERT … ON CONFLICT upsert behaviour in save()', function (): void {
    $reflection = new ReflectionClass(CategoryTreeMarketAssignmentRepository::class);

    expect($reflection->getNamespaceName())->toBe('Markommerce\\CatalogMarket\\Repositories');
    expect($reflection->implementsInterface(CategoryTreeMarketAssignmentRepositoryInterface::class))->toBeTrue();

    // Verify the upsert SQL is preserved verbatim in save()
    $method = $reflection->getMethod('save');
    $filename = $reflection->getFileName();
    $startLine = $method->getStartLine();
    $endLine = $method->getEndLine();

    $lines = file($filename);
    $methodBody = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

    expect($methodBody)->toContain('ON CONFLICT (market) DO UPDATE SET tree_id = EXCLUDED.tree_id');
});
