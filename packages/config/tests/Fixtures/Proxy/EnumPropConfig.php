<?php

declare(strict_types=1);

namespace Markommerce\Config\Tests\Fixtures\Proxy;

use Markommerce\Config\Attributes\Config;

class EnumPropConfig
{
    #[Config(key: 'proxy/enum.value')]
    public Color $color = Color::Red;
}
