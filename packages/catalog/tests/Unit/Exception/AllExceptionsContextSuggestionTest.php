<?php

declare(strict_types=1);

use Markommerce\Catalog\Exception\CategoryNotFoundException;
use Markommerce\Catalog\Exception\DuplicateSkuException;
use Markommerce\Catalog\Exception\InvalidCategoryDataException;
use Markommerce\Catalog\Exception\InvalidProductDataException;
use Markommerce\Catalog\Exception\ProductNotFoundException;

it('provides a non-empty context and suggestion on every factory method', function (): void {
    $exceptions = [
        ProductNotFoundException::forId(1),
        ProductNotFoundException::forSku('SKU-1'),
        CategoryNotFoundException::forId(1),
        DuplicateSkuException::forSku('SKU-1'),
        InvalidProductDataException::emptyName(),
        InvalidProductDataException::emptySku(),
        InvalidProductDataException::negativeBasePrice(-100),
        InvalidProductDataException::currencyMismatch('USD', 'EUR'),
        InvalidCategoryDataException::emptyName(),
    ];

    foreach ($exceptions as $exception) {
        expect($exception->getContext())->not->toBeEmpty();
        expect($exception->getSuggestion())->not->toBeEmpty();
    }
});
