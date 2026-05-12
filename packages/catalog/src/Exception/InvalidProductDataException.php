<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Exception;

use Marko\Core\Exceptions\MarkoException;

class InvalidProductDataException extends MarkoException
{
    public static function emptyName(): self
    {
        return new self(
            message: 'Product name must not be empty',
            context: 'While validating product data',
            suggestion: 'Provide a non-empty name for the product',
        );
    }

    public static function emptySku(): self
    {
        return new self(
            message: 'Product SKU must not be empty',
            context: 'While validating product data',
            suggestion: 'Provide a non-empty SKU for the product',
        );
    }

    public static function negativeBasePrice(int $amount): self
    {
        return new self(
            message: "Product base price must not be negative, got {$amount}",
            context: 'While validating product data',
            suggestion: 'Provide a non-negative base price for the product',
        );
    }

    public static function currencyMismatch(string $expected, string $actual): self
    {
        return new self(
            message: "Currency mismatch: expected \"{$expected}\" but got \"{$actual}\"",
            context: 'While validating product data',
            suggestion: "Ensure all monetary values use the same currency code: \"{$expected}\"",
        );
    }
}
