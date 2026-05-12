<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Support;

use Markommerce\Money\MoneyFactoryInterface;
use Markommerce\Money\MoneyInterface;

class FakeMoneyFactory implements MoneyFactoryInterface
{
    /** @var array<array{amount: int, currency: string|null}> */
    public array $calls = [];

    public function create(int $amount, ?string $currency = null): MoneyInterface
    {
        $this->calls[] = ['amount' => $amount, 'currency' => $currency];

        return new FakeMoney($amount, $currency ?? 'USD');
    }
}
