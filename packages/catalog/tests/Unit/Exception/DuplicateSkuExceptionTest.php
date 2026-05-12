<?php

declare(strict_types=1);

use Markommerce\Catalog\Exception\DuplicateSkuException;

it('constructs a DuplicateSkuException with the conflicting sku in the message', function (): void {
    $exception = DuplicateSkuException::forSku('SHIRT-L-RED');

    expect($exception->getMessage())->toContain('SHIRT-L-RED');
});
