<?php

declare(strict_types=1);

use Markommerce\Catalog\Exception\CategoryNotFoundException;

it('constructs a CategoryNotFoundException for a numeric id with the id in the message', function (): void {
    $exception = CategoryNotFoundException::forId(7);

    expect($exception->getMessage())->toContain('7');
});
