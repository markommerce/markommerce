<?php

declare(strict_types=1);

use Markommerce\Criteria\Exceptions\IncompatiblePositionException;

it('builds an IncompatiblePositionException naming the expected and actual token type', function (): void {
    $exception = IncompatiblePositionException::expected('offset', 'cursor');

    expect($exception->getMessage())->toContain('offset')
        ->and($exception->getMessage())->toContain('cursor');
});

it(
    'the IncompatiblePositionException carries context and a suggestion to restart from the first page',
    function (): void {
        $exception = IncompatiblePositionException::expected('offset', 'cursor');

        expect($exception->getContext())->not->toBeEmpty()
            ->and($exception->getSuggestion())->not->toBeEmpty()
            ->and($exception->getSuggestion())->toContain('first page');
    },
);
