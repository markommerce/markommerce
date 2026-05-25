<?php

declare(strict_types=1);

namespace Markommerce\Config\Tests\Fixtures\Proxy;

use Markommerce\Config\Attributes\Config;

class UnionTypePropConfig
{
    #[Config(key: 'proxy/union.value')]
    public int|string $value = 42;
}
