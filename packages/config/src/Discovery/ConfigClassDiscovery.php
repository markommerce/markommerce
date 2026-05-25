<?php

declare(strict_types=1);

namespace Markommerce\Config\Discovery;

use Marko\Core\Module\ModuleRepositoryInterface;
use Markommerce\Config\Attributes\Config;
use ReflectionClass;

class ConfigClassDiscovery
{
    public function __construct(
        private readonly ModuleRepositoryInterface $moduleRepository,
    ) {}

    /**
     * Scan each module's src/ directory for PHP classes that have at least one
     * property carrying the #[Config] attribute.
     *
     * Uses a cheap file pre-filter (string search for `#[Config(`) before loading
     * the class via reflection to avoid loading every PHP file.
     *
     * @return list<class-string>
     */
    public function discover(): array
    {
        /** @var list<class-string> $found */
        $found = [];

        foreach ($this->moduleRepository->all() as $module) {
            $srcDir = $module->path . '/src';

            if (!is_dir($srcDir)) {
                continue;
            }

            $files = $this->findPhpFiles($srcDir);

            foreach ($files as $file) {
                $contents = file_get_contents($file);

                if ($contents === false) {
                    continue;
                }

                // Cheap pre-filter: skip files that don't mention #[Config(
                if (!str_contains($contents, '#[Config(')) {
                    continue;
                }

                $className = $this->extractClassName($contents);

                if ($className === null) {
                    continue;
                }

                if (!class_exists($className)) {
                    continue;
                }

                $reflection = new ReflectionClass($className);

                foreach ($reflection->getProperties() as $property) {
                    if (count($property->getAttributes(Config::class)) > 0) {
                        $found[] = $className;
                        break;
                    }
                }
            }
        }

        return $found;
    }

    /**
     * Recursively find all PHP files under a directory.
     *
     * @return list<string>
     */
    private function findPhpFiles(string $dir): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * Extract the fully-qualified class name from PHP source content.
     */
    private function extractClassName(string $contents): ?string
    {
        // Extract namespace
        $namespace = '';
        if (preg_match('/^namespace\s+([\w\\\\]+)\s*;/m', $contents, $matches)) {
            $namespace = $matches[1] . '\\';
        }

        // Extract class name (class, abstract class — not interface/trait/enum)
        if (!preg_match('/^(?:abstract\s+)?(?:readonly\s+)?class\s+(\w+)/m', $contents, $matches)) {
            return null;
        }

        /** @var class-string */
        return $namespace . $matches[1];
    }
}
