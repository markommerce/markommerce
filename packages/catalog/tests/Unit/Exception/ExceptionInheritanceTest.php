<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Markommerce\Catalog\Exception\CategoryNotFoundException;
use Markommerce\Catalog\Exception\DuplicateSkuException;
use Markommerce\Catalog\Exception\InvalidCategoryDataException;
use Markommerce\Catalog\Exception\InvalidProductDataException;
use Markommerce\Catalog\Exception\ProductNotFoundException;

it('has every catalog exception extend MarkoException', function (): void {
    expect(ProductNotFoundException::forId(1))->toBeInstanceOf(MarkoException::class);
    expect(ProductNotFoundException::forSku('SKU'))->toBeInstanceOf(MarkoException::class);
    expect(CategoryNotFoundException::forId(1))->toBeInstanceOf(MarkoException::class);
    expect(DuplicateSkuException::forSku('SKU'))->toBeInstanceOf(MarkoException::class);
    expect(InvalidProductDataException::emptyName())->toBeInstanceOf(MarkoException::class);
    expect(InvalidProductDataException::emptySku())->toBeInstanceOf(MarkoException::class);
    expect(InvalidProductDataException::negativeBasePrice(-1))->toBeInstanceOf(MarkoException::class);
    expect(InvalidProductDataException::currencyMismatch('USD', 'EUR'))->toBeInstanceOf(MarkoException::class);
    expect(InvalidCategoryDataException::emptyName())->toBeInstanceOf(MarkoException::class);
});
