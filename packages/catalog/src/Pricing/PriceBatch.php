<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Pricing;

use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Pricing\Exceptions\InvalidBatchKeyException;
use Markommerce\Money\Currency;

class PriceBatch
{
    /**
     * @param array<array-key, Product> $products
     * @param array<array-key, ?string> $amounts
     */
    public function __construct(
        private array $products,
        private Currency $currency,
        private array $amounts = [],
    ) {}

    /**
     * @param array<array-key, Product> $products
     */
    public static function of(
        array $products,
        Currency $currency,
    ): self {
        return new self($products, $currency);
    }

    /**
     * @return array<array-key, Product>
     */
    public function products(): array
    {
        return $this->products;
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function amount(int|string $key): ?string
    {
        return $this->amounts[$key] ?? null;
    }

    /**
     * @throws InvalidBatchKeyException
     */
    public function setAmount(
        int|string $key,
        ?string $amount,
    ): void {
        if (!array_key_exists($key, $this->products)) {
            throw InvalidBatchKeyException::forKey($key);
        }

        $this->amounts[$key] = $amount;
    }

    /**
     * @return list<array-key>
     */
    public function keys(): array
    {
        return array_keys($this->products);
    }
}
