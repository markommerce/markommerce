<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Markommerce\Catalog\Exceptions\CircularNodeReferenceException;

it('CircularNodeReferenceException::forNodeAndParent reports both node ids and explains the cycle', function (): void {
    $exception = CircularNodeReferenceException::forNodeAndParent(nodeId: 5, proposedParentId: 10);

    expect($exception)->toBeInstanceOf(CircularNodeReferenceException::class)
        ->and($exception)->toBeInstanceOf(MarkoException::class)
        ->and($exception->getMessage())->toContain('5')
        ->and($exception->getMessage())->toContain('10')
        ->and($exception->getContext())->not->toBeEmpty()
        ->and($exception->getSuggestion())->not->toBeEmpty();
});
