<?php

declare(strict_types=1);

use Markommerce\Catalog\Exception\ProductNotFoundException;

it('constructs a ProductNotFoundException for a numeric id with the id in the message', function (): void {
    $exception = ProductNotFoundException::forId(42);

    expect($exception->getMessage())->toContain('42');
});

it('constructs a ProductNotFoundException for a sku with the sku in the message', function (): void {
    $exception = ProductNotFoundException::forSku('WIDGET-001');

    expect($exception->getMessage())->toContain('WIDGET-001');
});
