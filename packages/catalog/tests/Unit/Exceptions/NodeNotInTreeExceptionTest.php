<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Markommerce\Catalog\Exceptions\NodeNotInTreeException;

it('NodeNotInTreeException::forNodeAndTree reports expected vs actual tree id', function (): void {
    $exception = NodeNotInTreeException::forNodeAndTree(nodeId: 3, expectedTreeId: 1, actualTreeId: 2);

    expect($exception)->toBeInstanceOf(NodeNotInTreeException::class)
        ->and($exception)->toBeInstanceOf(MarkoException::class)
        ->and($exception->getMessage())->toContain('3')
        ->and($exception->getMessage())->toContain('1')
        ->and($exception->getMessage())->toContain('2')
        ->and($exception->getContext())->not->toBeEmpty()
        ->and($exception->getSuggestion())->not->toBeEmpty();
});

it('NodeNotInTreeException::forParentMismatch reports expected vs actual parent id', function (): void {
    $exception = NodeNotInTreeException::forParentMismatch(nodeId: 7, expectedParentNodeId: 4, actualParentNodeId: 9);

    expect($exception)->toBeInstanceOf(NodeNotInTreeException::class)
        ->and($exception)->toBeInstanceOf(MarkoException::class)
        ->and($exception->getMessage())->toContain('7')
        ->and($exception->getMessage())->toContain('4')
        ->and($exception->getMessage())->toContain('9')
        ->and($exception->getContext())->not->toBeEmpty()
        ->and($exception->getSuggestion())->not->toBeEmpty();
});
