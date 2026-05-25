<?php

declare(strict_types=1);

namespace Markommerce\Config\Tests\Fixtures\Proxy;

use Markommerce\Config\Attributes\Config;

class ReadonlyPropConfig
{
    #[Config(key: 'proxy/readonly.value')]
    public readonly string $value;
}
