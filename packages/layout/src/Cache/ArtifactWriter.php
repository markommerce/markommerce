<?php

declare(strict_types=1);

namespace Markommerce\Layout\Cache;

class ArtifactWriter implements ArtifactWriterInterface
{
    private PhpCodeEmitter $emitter;

    public function __construct(
        private string $path,
    ) {
        $this->emitter = new PhpCodeEmitter();
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @param array<string, PreparedTree> $trees
     */
    public function write(array $trees): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $code = $this->emitter->emitArtifact($trees);
        file_put_contents($this->path, $code);
    }
}
