<?php

declare(strict_types=1);

namespace Markommerce\Currency\Config;

use Markommerce\Config\Attributes\Config;

class CurrencyConfig
{
    #[Config(key: 'currency/base')]
    public string $base = 'USD';
}
