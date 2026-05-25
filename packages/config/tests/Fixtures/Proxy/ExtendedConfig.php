<?php

declare(strict_types=1);

namespace Markommerce\Config\Tests\Fixtures\Proxy;

use Markommerce\Config\Attributes\Config;

class ExtendedConfig extends BaseConfig
{
    #[Config(key: 'test/extended.value')]
    public string $extra = 'extended';
}
