<?php

declare(strict_types=1);

use Marko\Database\Attributes\Column;
use Markommerce\CatalogPriceIndex\Entity\ProductPriceIndexEntry;
use Markommerce\Scope\Storage\HasScopesInterface;

it('stores scope overrides on the entry via the scopes column', function (): void {
    $entry = new ProductPriceIndexEntry();

    $entry->setOverride('market:us', 'amount', '19.99');

    expect($entry)->toBeInstanceOf(HasScopesInterface::class)
        ->and($entry->override('market:us', 'amount'))->toBe('19.99')
        ->and($entry->scopes)->toBe(['market:us' => ['amount' => '19.99']]);
});

it('exposes product id amount and currency code columns', function (): void {
    $reflection = new ReflectionClass(ProductPriceIndexEntry::class);

    $entry = new ProductPriceIndexEntry();
    $entry->productId = 42;
    $entry->amount = '9.99';
    $entry->currencyCode = 'USD';

    expect($entry->productId)->toBe(42)
        ->and($entry->amount)->toBe('9.99')
        ->and($entry->currencyCode)->toBe('USD')
        ->and($entry->id)->toBeNull();

    // Verify Column attributes exist on these properties
    $productIdProp = $reflection->getProperty('productId');
    $amountProp = $reflection->getProperty('amount');
    $currencyCodeProp = $reflection->getProperty('currencyCode');
    $idProp = $reflection->getProperty('id');

    expect($productIdProp->getAttributes(Column::class))->toHaveCount(1)
        ->and($amountProp->getAttributes(Column::class))->toHaveCount(1)
        ->and($currencyCodeProp->getAttributes(Column::class))->toHaveCount(1)
        ->and($idProp->getAttributes(Column::class))->toHaveCount(1);
});
