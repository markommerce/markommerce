<?php

declare(strict_types=1);

namespace Markommerce\Config\Tests\Fixtures\Proxy;

use Markommerce\Config\Attributes\Config;

class BaseConfig
{
    #[Config(key: 'test/base.value')]
    public string $value = 'base';
}
