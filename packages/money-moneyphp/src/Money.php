<?php

declare(strict_types=1);

namespace Markommerce\Money\Moneyphp;

use Markommerce\Money\MoneyException;
use Markommerce\Money\MoneyInterface;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Formatter\IntlMoneyFormatter;
use NumberFormatter;

readonly class Money implements MoneyInterface
{
    private \Money\Money $money;

    /**
     * @param non-empty-string $currency
     */
    public function __construct(private int $amount, private string $currency)
    {
        $this->money = new \Money\Money((string) $this->amount, new Currency($this->currency));
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    /**
     * @throws MoneyException
     */
    public function add(MoneyInterface $money): MoneyInterface
    {
        $otherCurrency = $money->currency();
        if ($otherCurrency !== $this->currency) {
            throw MoneyException::currencyMismatch($this->currency, $otherCurrency, 'add');
        }

        /** @var non-empty-string $otherCurrency */
        $other = new \Money\Money((string) $money->amount(), new Currency($otherCurrency));
        $result = $this->money->add($other);

        return new self((int) $result->getAmount(), $this->currency);
    }

    /**
     * @throws MoneyException
     */
    public function subtract(MoneyInterface $money): MoneyInterface
    {
        $otherCurrency = $money->currency();
        if ($otherCurrency !== $this->currency) {
            throw MoneyException::currencyMismatch($this->currency, $otherCurrency, 'subtract');
        }

        /** @var non-empty-string $otherCurrency */
        $other = new \Money\Money((string) $money->amount(), new Currency($otherCurrency));
        $result = $this->money->subtract($other);

        return new self((int) $result->getAmount(), $this->currency);
    }

    /**
     * @throws MoneyException
     */
    public function multiply(string $factor): MoneyInterface
    {
        if (!is_numeric($factor)) {
            throw MoneyException::invalidMultiplyFactor($factor, 'factor must be a numeric string');
        }

        $result = $this->money->multiply($factor);

        return new self((int) $result->getAmount(), $this->currency);
    }

    /**
     * @param array<int|float> $ratios
     *
     * @return array<MoneyInterface>
     *
     * @throws MoneyException
     */
    public function allocate(array $ratios): array
    {
        if (empty($ratios)) {
            throw MoneyException::invalidAllocationRatios('ratios array must not be empty');
        }

        foreach ($ratios as $ratio) {
            if ($ratio <= 0) {
                throw MoneyException::invalidAllocationRatios('all ratios must be positive');
            }
        }

        $sum = array_sum($ratios);
        if ($sum <= 0) {
            throw MoneyException::invalidAllocationRatios('ratios must sum to a value greater than zero');
        }

        $results = $this->money->allocate($ratios);

        return array_map(
            fn (\Money\Money $m): self => new self((int) $m->getAmount(), $this->currency),
            $results
        );
    }

    public function equals(MoneyInterface $money): bool
    {
        if ($money->currency() !== $this->currency) {
            return false;
        }

        return $this->amount === $money->amount();
    }

    /**
     * @throws MoneyException
     */
    public function greaterThan(MoneyInterface $money): bool
    {
        $otherCurrency = $money->currency();
        if ($otherCurrency !== $this->currency) {
            throw MoneyException::currencyMismatch($this->currency, $otherCurrency, 'greaterThan');
        }

        /** @var non-empty-string $otherCurrency */
        $other = new \Money\Money((string) $money->amount(), new Currency($otherCurrency));

        return $this->money->greaterThan($other);
    }

    /**
     * @throws MoneyException
     */
    public function lessThan(MoneyInterface $money): bool
    {
        $otherCurrency = $money->currency();
        if ($otherCurrency !== $this->currency) {
            throw MoneyException::currencyMismatch($this->currency, $otherCurrency, 'lessThan');
        }

        /** @var non-empty-string $otherCurrency */
        $other = new \Money\Money((string) $money->amount(), new Currency($otherCurrency));

        return $this->money->lessThan($other);
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function format(?string $locale = null): string
    {
        $formatter = new IntlMoneyFormatter(
            new NumberFormatter($locale ?? \Locale::getDefault(), NumberFormatter::CURRENCY),
            new ISOCurrencies()
        );

        return $formatter->format($this->money);
    }
}
