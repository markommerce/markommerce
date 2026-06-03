<?php

declare(strict_types=1);

use Markommerce\Pricing\Contracts\PriceResolverInterface;
use Markommerce\Pricing\PriceResolver;

return [
    'bindings' => [
        PriceResolverInterface::class => PriceResolver::class,
    ],
];
