<?php

declare(strict_types=1);

use Markommerce\Catalog\Exception\InvalidCategoryDataException;

it('constructs InvalidCategoryDataException emptyName with a clear message', function (): void {
    $exception = InvalidCategoryDataException::emptyName();

    expect($exception->getMessage())->not->toBeEmpty();
});
