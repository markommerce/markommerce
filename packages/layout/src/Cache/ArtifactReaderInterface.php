<?php

declare(strict_types=1);

namespace Markommerce\Layout\Cache;

use RuntimeException;

interface ArtifactReaderInterface
{
    /**
     * @return array<string, PreparedTree>
     * @throws RuntimeException
     */
    public function read(): array;
}
