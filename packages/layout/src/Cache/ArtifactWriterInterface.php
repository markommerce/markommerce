<?php

declare(strict_types=1);

namespace Markommerce\Layout\Cache;

interface ArtifactWriterInterface
{
    /**
     * @param array<string, PreparedTree> $trees
     */
    public function write(array $trees): void;

    public function getPath(): string;
}
