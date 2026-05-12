<?php

declare(strict_types=1);

namespace Markommerce\Money;

interface MoneyInterface
{
    public function amount(): int;

    public function currency(): string;

    /**
     * @throws MoneyException
     */
    public function add(MoneyInterface $money): MoneyInterface;

    /**
     * @throws MoneyException
     */
    public function subtract(MoneyInterface $money): MoneyInterface;

    /**
     * @param string $factor A numeric string (e.g. "1.5"). Implementations throw MoneyException on a non-numeric string or invalid factor.
     *
     * @throws MoneyException
     */
    public function multiply(string $factor): MoneyInterface;

    /**
     * @param array<int|float> $ratios Ratios must be positive and sum to > 0.
     *
     * @return array<MoneyInterface>
     *
     * @throws MoneyException
     */
    public function allocate(array $ratios): array;

    public function equals(MoneyInterface $money): bool;

    /**
     * Implementations throw MoneyException on currency mismatch.
     *
     * @throws MoneyException
     */
    public function greaterThan(MoneyInterface $money): bool;

    /**
     * Implementations throw MoneyException on currency mismatch.
     *
     * @throws MoneyException
     */
    public function lessThan(MoneyInterface $money): bool;

    public function isZero(): bool;

    public function format(?string $locale = null): string;
}
