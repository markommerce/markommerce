<?php

declare(strict_types=1);

use Markommerce\Criteria\Exceptions\PageOutOfRangeException;

it('builds a PageOutOfRangeException naming the requested page and total pages', function (): void {
    $exception = PageOutOfRangeException::forPage(5, 3);

    expect($exception->getMessage())->toContain('5')
        ->and($exception->getMessage())->toContain('3');
});

it('the PageOutOfRangeException carries context and a suggestion', function (): void {
    $exception = PageOutOfRangeException::forPage(5, 3);

    expect($exception->getContext())->not->toBeEmpty()
        ->and($exception->getSuggestion())->not->toBeEmpty();
});
