<?php

declare(strict_types=1);

namespace Markommerce\Config\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class ProxyNotGeneratedException extends MarkoException
{
    /**
     * @param class-string $configClass
     */
    public static function forClass(string $configClass): self
    {
        return new self(
            message: "Typed config proxy for '$configClass' has not been generated",
            context: "Resolving typed config proxy for '$configClass' at runtime — the generated proxy class was not found",
            suggestion: "Run 'php artisan config:generate' (or the equivalent console command) to generate proxy classes before deploying",
        );
    }
}
