<?php

declare(strict_types=1);

namespace Markommerce\Layout\Cache;

class ArtifactReader implements ArtifactReaderInterface
{
    public function __construct(
        private string $path,
    ) {}

    /**
     * @return array<string, PreparedTree>
     * @throws \RuntimeException
     */
    public function read(): array
    {
        if (!file_exists($this->path)) {
            throw new \RuntimeException(
                sprintf(
                    'Layout artifact not found at "%s". Run "layout:compile" to generate it.',
                    $this->path,
                ),
            );
        }

        /** @var array<string, PreparedTree> $artifact */
        $artifact = require $this->path;
        return $artifact;
    }
}
