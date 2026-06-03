<?php

declare(strict_types=1);

namespace Markommerce\Tax\Config;

use Markommerce\Config\Attributes\Config;

class TaxConfig
{
    #[Config(key: 'tax/prices_include_tax')]
    public bool $pricesIncludeTax = false;
}
