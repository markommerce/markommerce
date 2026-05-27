<?php

declare(strict_types=1);

it('docs/src/content/docs/packages/catalog-market.md covers resolver, assignment service, and delete plugin', function (): void {
    $file = dirname(__DIR__, 2) . '/docs/src/content/docs/packages/catalog-market.md';

    expect(file_exists($file))->toBeTrue();

    $content = (string) file_get_contents($file);

    expect($content)->toContain('CategoryTreeMarketResolver')
        ->and($content)->toContain('CategoryTreeMarketAssignmentService')
        ->and($content)->toContain('CategoryTreeServiceDeletePlugin');
});
