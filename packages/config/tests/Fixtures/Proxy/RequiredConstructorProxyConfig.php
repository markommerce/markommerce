<?php

declare(strict_types=1);

namespace Markommerce\Config\Tests\Fixtures\Proxy;

use Markommerce\Config\Attributes\Config;

class RequiredConstructorProxyConfig
{
    public function __construct(
        private readonly string $requiredParam,
    ) {}

    #[Config(key: 'proxy/ctor.value')]
    public int $value = 1;
}
