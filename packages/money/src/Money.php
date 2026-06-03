<?php

declare(strict_types=1);

namespace Markommerce\Money;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\DivisionByZeroException as BrickDivisionByZeroException;
use Markommerce\Money\Exceptions\CurrencyMismatchException;
use Markommerce\Money\Exceptions\DivisionByZeroException;

readonly class Money
{
    private BigDecimal $bigDecimal;

    private function __construct(
        BigDecimal $bigDecimal,
        private Currency $currency,
    ) {
        $this->bigDecimal = $bigDecimal;
    }

    public static function of(
        string|int $amount,
        Currency $currency,
    ): self
    {
        return new self(BigDecimal::of($amount), $currency);
    }

    public static function ofMinor(
        int $minor,
        Currency $currency,
    ): self
    {
        $bigDecimal = BigDecimal::ofUnscaledValue($minor, $currency->scale);

        return new self($bigDecimal, $currency);
    }

    public function amount(): string
    {
        return (string) $this->bigDecimal->toScale($this->currency->scale);
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    /**
     * @throws CurrencyMismatchException
     */
    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);

        return new self(
            $this->bigDecimal->plus($other->bigDecimal),
            $this->currency,
        );
    }

    /**
     * @throws CurrencyMismatchException
     */
    public function subtract(Money $other): self
    {
        $this->assertSameCurrency($other);

        return new self(
            $this->bigDecimal->minus($other->bigDecimal),
            $this->currency,
        );
    }

    public function multiply(
        string|int $factor,
        RoundingMode $mode,
    ): self
    {
        $result = $this->bigDecimal
            ->multipliedBy(BigDecimal::of($factor))
            ->toScale($this->currency->scale, $mode->toBrick());

        return new self($result, $this->currency);
    }

    /**
     * @throws DivisionByZeroException
     */
    public function divide(
        string|int $divisor,
        RoundingMode $mode,
    ): self
    {
        try {
            $result = $this->bigDecimal->dividedBy(
                BigDecimal::of($divisor),
                $this->currency->scale,
                $mode->toBrick(),
            );
        } catch (BrickDivisionByZeroException) {
            throw DivisionByZeroException::forDivisionByZero();
        }

        return new self($result, $this->currency);
    }

    /**
     * @param array<int> $ratios
     *
     * @return array<Money>
     */
    public function allocate(array $ratios): array
    {
        $total = array_sum($ratios);
        $scale = $this->currency->scale;

        $unscaledValue = $this->bigDecimal->toScale($scale)->getUnscaledValue()->toInt();

        $allocated = [];
        $remainder = $unscaledValue;

        foreach ($ratios as $ratio) {
            $share = intdiv($unscaledValue * $ratio, $total);
            $allocated[] = $share;
            $remainder -= $share;
        }

        for ($i = 0; $remainder > 0; $i++, $remainder--) {
            $allocated[$i]++;
        }

        return array_map(
            fn (int $minor) => self::ofMinor($minor, $this->currency),
            $allocated,
        );
    }

    /**
     * @throws CurrencyMismatchException
     */
    public function equals(Money $other): bool
    {
        if (!$this->currency->equals($other->currency)) {
            return false;
        }

        return $this->bigDecimal->compareTo($other->bigDecimal) === 0;
    }

    public function isZero(): bool
    {
        return $this->bigDecimal->isZero();
    }

    public function isPositive(): bool
    {
        return $this->bigDecimal->isPositive();
    }

    public function isNegative(): bool
    {
        return $this->bigDecimal->isNegative();
    }

    /**
     * @throws CurrencyMismatchException
     */
    public function compareTo(Money $other): int
    {
        $this->assertSameCurrency($other);

        return $this->bigDecimal->compareTo($other->bigDecimal);
    }

    /**
     * @throws CurrencyMismatchException
     */
    private function assertSameCurrency(Money $other): void
    {
        if (!$this->currency->equals($other->currency)) {
            throw CurrencyMismatchException::forMismatch($this->currency, $other->currency);
        }
    }
}
