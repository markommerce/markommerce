<?php

declare(strict_types=1);

namespace Markommerce\Scope\Tests\Support;

use Markommerce\Scope\Attributes\Scoped;

/**
 * An entity that has one #[Scoped(axes: ['locale'])] property AND will also
 * receive a programmatic registration for the same property under a different
 * axis via the bridge. Used to verify union behaviour.
 */
class MixedEntity
{
    #[Scoped(axes: ['locale'])]
    public string $title = '';

    public string $slug = '';
}
