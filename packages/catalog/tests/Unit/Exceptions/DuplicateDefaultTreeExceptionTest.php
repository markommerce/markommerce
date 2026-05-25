<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Markommerce\Catalog\Exceptions\DuplicateDefaultTreeException;

it('DuplicateDefaultTreeException::forCode reports the conflicting code in message and context', function (): void {
    $exception = DuplicateDefaultTreeException::forCode('main-tree');

    expect($exception)->toBeInstanceOf(DuplicateDefaultTreeException::class)
        ->and($exception)->toBeInstanceOf(MarkoException::class)
        ->and($exception->getMessage())->toContain('main-tree')
        ->and($exception->getContext())->toContain('main-tree')
        ->and($exception->getSuggestion())->not->toBeEmpty();
});
