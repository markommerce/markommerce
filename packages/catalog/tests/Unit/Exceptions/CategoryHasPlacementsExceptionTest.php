<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Markommerce\Catalog\Exceptions\CategoryHasPlacementsException;

it('CategoryHasPlacementsException::forCategory reports category id and placement count', function (): void {
    $exception = CategoryHasPlacementsException::forCategory(categoryId: 12, placementCount: 5);

    expect($exception)->toBeInstanceOf(CategoryHasPlacementsException::class)
        ->and($exception)->toBeInstanceOf(MarkoException::class)
        ->and($exception->getMessage())->toContain('12')
        ->and($exception->getMessage())->toContain('5')
        ->and($exception->getContext())->not->toBeEmpty()
        ->and($exception->getSuggestion())->not->toBeEmpty();
});
