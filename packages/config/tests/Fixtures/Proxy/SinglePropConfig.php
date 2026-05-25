<?php

declare(strict_types=1);

namespace Markommerce\Config\Tests\Fixtures\Proxy;

use Markommerce\Config\Attributes\Config;

class SinglePropConfig
{
    #[Config(key: 'proxy/single.value')]
    public string $message = 'hello';
}
