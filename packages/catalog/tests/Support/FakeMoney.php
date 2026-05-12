<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Support;

use Markommerce\Money\MoneyInterface;

class FakeMoney implements MoneyInterface
{
    public function __construct(
        private int $amount,
        private string $currency,
    ) {
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function equals(MoneyInterface $money): bool
    {
        return $this->amount === $money->amount() && $this->currency === $money->currency();
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function add(MoneyInterface $money): MoneyInterface
    {
        throw new \LogicException('not implemented in FakeMoney');
    }

    public function subtract(MoneyInterface $money): MoneyInterface
    {
        throw new \LogicException('not implemented in FakeMoney');
    }

    public function multiply(string $factor): MoneyInterface
    {
        throw new \LogicException('not implemented in FakeMoney');
    }

    /**
     * @param array<int|float> $ratios
     *
     * @return array<MoneyInterface>
     */
    public function allocate(array $ratios): array
    {
        throw new \LogicException('not implemented in FakeMoney');
    }

    public function greaterThan(MoneyInterface $money): bool
    {
        throw new \LogicException('not implemented in FakeMoney');
    }

    public function lessThan(MoneyInterface $money): bool
    {
        throw new \LogicException('not implemented in FakeMoney');
    }

    public function format(?string $locale = null): string
    {
        throw new \LogicException('not implemented in FakeMoney');
    }
}
