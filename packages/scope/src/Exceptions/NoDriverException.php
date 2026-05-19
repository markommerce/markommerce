<?php

declare(strict_types=1);

namespace Markommerce\Scope\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class NoDriverException extends MarkoException
{
    private const array DRIVER_PACKAGES = [
        'markommerce/scope-pgsql',
    ];

    public static function noDriverInstalled(): self
    {
        $packageList = implode("\n", array_map(
            fn (string $pkg) => "- `composer require $pkg`",
            self::DRIVER_PACKAGES,
        ));

        return new self(
            message: 'No scope sort renderer driver installed.',
            context: 'Attempted to resolve ScopeSortRendererInterface but no implementation is bound.',
            suggestion: "Install a scope driver:\n$packageList",
        );
    }
}
