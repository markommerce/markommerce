<?php

declare(strict_types=1);

namespace Markommerce\Config\Proxy;

class ProxyWriter
{
    /**
     * Writes the generated proxy source to the filesystem, mirroring the FQN as a path.
     *
     * @param string $generatedFqn Fully-qualified class name of the generated proxy
     * @param string $source PHP source code to write
     * @param string $targetDir Base directory for generated files
     * @return string Absolute path to the written file
     */
    public function write(
        string $generatedFqn,
        string $source,
        string $targetDir,
    ): string {
        $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $generatedFqn) . '.php';
        $absolutePath = rtrim($targetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $relativePath;

        $directory = dirname($absolutePath);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($absolutePath, $source);

        return $absolutePath;
    }
}
