<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Markommerce\Catalog\Exceptions\TreeHasMarketAssignmentsException;

it('TreeHasMarketAssignmentsException::forTreeId reports the markets that still reference the tree', function (): void {
    $exception = TreeHasMarketAssignmentsException::forTreeId(3, ['us', 'eu']);

    expect($exception)->toBeInstanceOf(TreeHasMarketAssignmentsException::class)
        ->and($exception)->toBeInstanceOf(MarkoException::class)
        ->and($exception->getMessage())->toContain('3')
        ->and($exception->getMessage())->toContain('us')
        ->and($exception->getMessage())->toContain('eu')
        ->and($exception->getContext())->not->toBeEmpty()
        ->and($exception->getSuggestion())->not->toBeEmpty();
});
