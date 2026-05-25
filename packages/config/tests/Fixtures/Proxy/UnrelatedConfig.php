<?php

declare(strict_types=1);

namespace Markommerce\Config\Tests\Fixtures\Proxy;

use Markommerce\Config\Attributes\Config;

class UnrelatedConfig
{
    #[Config(key: 'test/unrelated.value')]
    public string $data = 'unrelated';
}
