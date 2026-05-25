<?php

declare(strict_types=1);

namespace Markommerce\Layout\Discovery;

use Marko\Core\Module\ModuleRepositoryInterface;
use Markommerce\Layout\Exceptions\InvalidLayoutFileException;
use Markommerce\Layout\Layout;
use Markommerce\Layout\LayoutExtension;

readonly class LayoutDiscovery
{
    public function __construct(
        private ModuleRepositoryInterface $moduleRepository,
    ) {}

    /**
     * Scan all modules for layout and extension files.
     *
     * @throws InvalidLayoutFileException
     */
    public function discover(): DiscoveryResult
    {
        $layouts = [];
        $extensions = [];

        foreach ($this->moduleRepository->all() as $module) {
            $layoutDir = $module->path . '/layout';

            if (!is_dir($layoutDir)) {
                continue;
            }

            foreach ($this->findPhpFiles($layoutDir) as $filePath) {
                $value = require $filePath;

                if (!$value instanceof Layout) {
                    $actualType = get_debug_type($value);
                    throw InvalidLayoutFileException::forWrongType($filePath, $actualType);
                }

                $layouts[] = new DiscoveredLayout(layout: $value, sourceFile: $filePath);
            }

            $extensionsDir = $layoutDir . '/extensions';

            if (!is_dir($extensionsDir)) {
                continue;
            }

            foreach ($this->findPhpFiles($extensionsDir) as $filePath) {
                $value = require $filePath;

                if (!$value instanceof LayoutExtension) {
                    $actualType = get_debug_type($value);
                    throw InvalidLayoutFileException::forWrongType($filePath, $actualType);
                }

                $extensions[] = new DiscoveredExtension(extension: $value, sourceFile: $filePath);
            }
        }

        return new DiscoveryResult(layouts: $layouts, extensions: $extensions);
    }

    /**
     * @return list<string>
     */
    private function findPhpFiles(string $directory): array
    {
        $files = glob($directory . '/*.php');

        if ($files === false) {
            return [];
        }

        return array_values($files);
    }
}
