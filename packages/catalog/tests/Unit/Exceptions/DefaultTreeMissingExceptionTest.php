<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Markommerce\Catalog\Exceptions\DefaultTreeMissingException;

it(
    'DefaultTreeMissingException::forResolution returns instance with descriptive message, context, and suggestion',
    function (): void {
        $exception = DefaultTreeMissingException::forResolution();

        expect($exception)->toBeInstanceOf(DefaultTreeMissingException::class)
            ->and($exception)->toBeInstanceOf(MarkoException::class)
            ->and($exception->getMessage())->not->toBeEmpty()
            ->and($exception->getContext())->not->toBeEmpty()
            ->and($exception->getSuggestion())->not->toBeEmpty();
    },
);
