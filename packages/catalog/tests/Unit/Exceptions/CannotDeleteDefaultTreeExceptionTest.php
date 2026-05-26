<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Markommerce\Catalog\Exceptions\CannotDeleteDefaultTreeException;

it('CannotDeleteDefaultTreeException::forTreeId reports the tree id and explains why deletion is blocked', function (): void {
    $exception = CannotDeleteDefaultTreeException::forTreeId(5);

    expect($exception)->toBeInstanceOf(CannotDeleteDefaultTreeException::class)
        ->and($exception)->toBeInstanceOf(MarkoException::class)
        ->and($exception->getMessage())->toContain('5')
        ->and($exception->getContext())->not->toBeEmpty()
        ->and($exception->getSuggestion())->not->toBeEmpty();
});
