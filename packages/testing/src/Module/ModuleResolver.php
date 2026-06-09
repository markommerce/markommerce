<?php

declare(strict_types=1);

namespace Markommerce\Testing\Module;

use Marko\Core\Module\ManifestParser;
use Marko\Core\Module\ModuleDiscovery;
use Marko\Core\Module\ModuleManifest;

/**
 * Resolves marko modules from composer's installed.json.
 *
 * Turns a set of root package names into the full transitive closure of marko
 * modules by reading require entries and filtering to only packages that declare
 * extra.marko.module = true.
 */
readonly class ModuleResolver
{
    private ModuleDiscovery $discovery;

    public function __construct(
        private string $vendorDir,
    ) {
        $this->discovery = new ModuleDiscovery(new ManifestParser());
    }

    /**
     * Return every installed marko module.
     *
     * Delegates to ModuleDiscovery::discoverInVendor which filesystem-scans
     * the vendor directory, filters to marko modules, and builds full ModuleManifests.
     *
     * @return array<ModuleManifest>
     */
    public function resolveAllInstalled(): array
    {
        return $this->discovery->discoverInVendor($this->vendorDir);
    }

    /**
     * Return the transitive marko-module closure of the given root packages.
     *
     * Walks each package's require transitively, keeping only packages that
     * declare extra.marko.module = true.
     *
     * @param array<string> $rootPackageNames
     * @return array<ModuleManifest>
     */
    public function resolveFrom(array $rootPackageNames): array
    {
        $allModules = $this->resolveAllInstalled();

        /** @var array<string, ModuleManifest> $modulesByName */
        $modulesByName = [];

        foreach ($allModules as $manifest) {
            $modulesByName[$manifest->name] = $manifest;
        }

        $installedPackages = $this->readInstalledPackages();

        $visited = [];
        $result = [];

        $queue = $rootPackageNames;

        while ($queue !== []) {
            $name = array_shift($queue);

            if (isset($visited[$name])) {
                continue;
            }

            $visited[$name] = true;

            if (isset($modulesByName[$name])) {
                $result[$name] = $modulesByName[$name];
            }

            $rawDeps = $installedPackages[$name]['require'] ?? [];
            $deps = is_array($rawDeps) ? $rawDeps : [];

            foreach (array_keys($deps) as $dep) {
                if (is_string($dep) && !$this->isPlatformRequirement($dep) && !isset($visited[$dep])) {
                    $queue[] = $dep;
                }
            }
        }

        return array_values($result);
    }

    /**
     * Read all packages from vendor/composer/installed.json.
     *
     * @return array<string, array<string, mixed>>
     */
    private function readInstalledPackages(): array
    {
        $installedPath = $this->vendorDir . '/composer/installed.json';
        $content = file_get_contents($installedPath);

        if ($content === false) {
            return [];
        }

        $data = json_decode($content, true);

        if (!is_array($data)) {
            return [];
        }

        $packages = $data['packages'] ?? $data;

        if (!is_array($packages)) {
            return [];
        }

        $result = [];

        foreach ($packages as $package) {
            if (is_array($package) && isset($package['name']) && is_string($package['name'])) {
                $result[$package['name']] = $package;
            }
        }

        return $result;
    }

    /**
     * Check if a package name is a platform requirement.
     *
     * Platform requirements (php, ext-*, lib-*) should be skipped when
     * computing the transitive module closure.
     */
    private function isPlatformRequirement(string $packageName): bool
    {
        return str_starts_with($packageName, 'php')
            || str_starts_with($packageName, 'ext-')
            || str_starts_with($packageName, 'lib-');
    }
}
