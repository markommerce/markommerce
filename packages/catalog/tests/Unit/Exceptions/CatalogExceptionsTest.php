<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Markommerce\Catalog\Exceptions\CategoryNotFoundException;
use Markommerce\Catalog\Exceptions\DuplicateSkuException;
use Markommerce\Catalog\Exceptions\ProductNotFoundException;

it('builds a ProductNotFoundException for an id with a message containing that id', function (): void {
    $exception = ProductNotFoundException::forId(42);

    expect($exception)->toBeInstanceOf(ProductNotFoundException::class)
        ->and($exception->getMessage())->toContain('42');
});

it('builds a ProductNotFoundException with non-empty context and suggestion', function (): void {
    $exception = ProductNotFoundException::forId(1);

    expect($exception->getContext())->not->toBeEmpty()
        ->and($exception->getSuggestion())->not->toBeEmpty();
});

it('builds a CategoryNotFoundException for an id with a message containing that id', function (): void {
    $exception = CategoryNotFoundException::forId(7);

    expect($exception)->toBeInstanceOf(CategoryNotFoundException::class)
        ->and($exception->getMessage())->toContain('7');
});

it('builds a DuplicateSkuException for a sku with a message containing that sku', function (): void {
    $exception = DuplicateSkuException::forSku('ABC-123');

    expect($exception)->toBeInstanceOf(DuplicateSkuException::class)
        ->and($exception->getMessage())->toContain('ABC-123');
});

it('builds a DuplicateSkuException with non-empty context and suggestion', function (): void {
    $exception = DuplicateSkuException::forSku('SKU-001');

    expect($exception->getContext())->not->toBeEmpty()
        ->and($exception->getSuggestion())->not->toBeEmpty();
});

it('makes every catalog exception an instance of the MarkoException base class', function (): void {
    expect(ProductNotFoundException::forId(1))->toBeInstanceOf(MarkoException::class)
        ->and(CategoryNotFoundException::forId(1))->toBeInstanceOf(MarkoException::class)
        ->and(DuplicateSkuException::forSku('SKU-1'))->toBeInstanceOf(MarkoException::class);
});
