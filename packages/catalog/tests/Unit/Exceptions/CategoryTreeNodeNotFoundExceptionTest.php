<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Markommerce\Catalog\Exceptions\CategoryTreeNodeNotFoundException;

it('CategoryTreeNodeNotFoundException::forId reports the requested id', function (): void {
    $exception = CategoryTreeNodeNotFoundException::forId(42);

    expect($exception)->toBeInstanceOf(CategoryTreeNodeNotFoundException::class)
        ->and($exception)->toBeInstanceOf(MarkoException::class)
        ->and($exception->getMessage())->toContain('42')
        ->and($exception->getContext())->not->toBeEmpty()
        ->and($exception->getSuggestion())->not->toBeEmpty();
});
