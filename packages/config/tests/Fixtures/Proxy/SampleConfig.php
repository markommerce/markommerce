<?php

declare(strict_types=1);

namespace Markommerce\Config\Tests\Fixtures\Proxy;

use Markommerce\Config\Attributes\Config;

class SampleConfig
{
    #[Config(key: 'test/greet')]
    public string $greeting = 'Hello';

    #[Config(key: 'test/count')]
    public int $itemCount = 10;
}
