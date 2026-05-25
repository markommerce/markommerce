<?php

declare(strict_types=1);

namespace Markommerce\Config\Generated\Markommerce\Config\Tests\Unit\Resolver\Fixtures;

use Markommerce\Config\ConfigResolver;
use Markommerce\Config\Tests\Unit\Resolver\Fixtures\SampleConfig;

class SampleConfig_Resolved extends SampleConfig
{
    public function __construct(private ConfigResolver $__resolver) {}

    public string $greeting {
        get => $this->__resolver->resolved(SampleConfig::class, 'greeting');
    }
}
