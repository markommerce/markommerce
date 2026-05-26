<?php

declare(strict_types=1);

namespace Markommerce\Scope\Tests\Support;

/**
 * A plain entity with no #[Scoped] attributes, used as a bridge-contribution
 * fixture. The bridge registers its properties programmatically so the test
 * isolates the registry path from the attribute-scan path.
 */
class PlainEntity
{
    public string $title = '';

    public string $summary = '';

    public int $position = 0;
}
