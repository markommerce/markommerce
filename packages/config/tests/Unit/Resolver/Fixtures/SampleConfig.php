<?php

declare(strict_types=1);

namespace Markommerce\Config\Tests\Unit\Resolver\Fixtures;

use Markommerce\Config\Attributes\Config;

class SampleConfig
{
    #[Config(key: 'test/greeting')]
    public string $greeting = 'Hello';
}
