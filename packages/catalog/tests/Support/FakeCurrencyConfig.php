<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Support;

use Markommerce\Money\CurrencyConfigInterface;

class FakeCurrencyConfig implements CurrencyConfigInterface
{
    public function __construct(public string $default = 'USD') {}

    public function getDefault(): string
    {
        return $this->default;
    }
}
