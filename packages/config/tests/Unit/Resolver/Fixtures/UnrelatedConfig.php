<?php

declare(strict_types=1);

namespace Markommerce\Config\Tests\Unit\Resolver\Fixtures;

use Markommerce\Config\Attributes\Config;

class UnrelatedConfig
{
    #[Config(key: 'test/unrelated')]
    public string $value = 'unrelated-default';
}
