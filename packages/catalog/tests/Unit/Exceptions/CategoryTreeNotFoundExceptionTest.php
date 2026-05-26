<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Markommerce\Catalog\Exceptions\CategoryTreeNotFoundException;

it('CategoryTreeNotFoundException::forId reports the requested id', function (): void {
    $exception = CategoryTreeNotFoundException::forId(12);

    expect($exception)->toBeInstanceOf(CategoryTreeNotFoundException::class)
        ->and($exception)->toBeInstanceOf(MarkoException::class)
        ->and($exception->getMessage())->toContain('12')
        ->and($exception->getContext())->not->toBeEmpty()
        ->and($exception->getSuggestion())->not->toBeEmpty();
});

it('CategoryTreeNotFoundException::forCode reports the requested code', function (): void {
    $exception = CategoryTreeNotFoundException::forCode('electronics');

    expect($exception)->toBeInstanceOf(CategoryTreeNotFoundException::class)
        ->and($exception)->toBeInstanceOf(MarkoException::class)
        ->and($exception->getMessage())->toContain('electronics')
        ->and($exception->getContext())->not->toBeEmpty()
        ->and($exception->getSuggestion())->not->toBeEmpty();
});
