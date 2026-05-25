<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Markommerce\Catalog\Exceptions\CategoryHasPlacementsException;
use Markommerce\Catalog\Exceptions\CategoryTreeNodeNotFoundException;
use Markommerce\Catalog\Exceptions\CircularNodeReferenceException;
use Markommerce\Catalog\Exceptions\NodeNotInTreeException;

it('each exception extends MarkoException and exposes message, context, and suggestion', function (): void {
    $exceptions = [
        CircularNodeReferenceException::forNodeAndParent(nodeId: 1, proposedParentId: 2),
        NodeNotInTreeException::forNodeAndTree(nodeId: 1, expectedTreeId: 1, actualTreeId: 2),
        NodeNotInTreeException::forParentMismatch(nodeId: 1, expectedParentNodeId: null, actualParentNodeId: 3),
        CategoryHasPlacementsException::forCategory(categoryId: 1, placementCount: 2),
        CategoryTreeNodeNotFoundException::forId(1),
    ];

    foreach ($exceptions as $exception) {
        expect($exception)->toBeInstanceOf(MarkoException::class)
            ->and($exception->getMessage())->not->toBeEmpty()
            ->and($exception->getContext())->not->toBeEmpty()
            ->and($exception->getSuggestion())->not->toBeEmpty();
    }
});
