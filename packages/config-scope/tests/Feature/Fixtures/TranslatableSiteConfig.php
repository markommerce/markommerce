<?php

declare(strict_types=1);

namespace Markommerce\ConfigScope\Tests\Feature\Fixtures;

use Markommerce\Config\Attributes\Config;
use Markommerce\Scope\Attributes\Scoped;

class TranslatableSiteConfig
{
    #[Config(key: 'test/site.greeting')]
    #[Scoped(axes: ['locale'])]
    public string $greeting = 'Hello';

    #[Config(key: 'test/site.tagline')]
    #[Scoped(axes: ['market'])]
    public string $tagline = 'Default tagline';
}
