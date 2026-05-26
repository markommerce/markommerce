<?php

declare(strict_types=1);

namespace Markommerce\Scope\Metadata;

use Markommerce\Scope\Attributes\Scoped;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use ReflectionClass;

/**
 * Builds and caches ScopeMetadata for entity classes using a dual contribution model.
 *
 * ## Dual contribution model
 * Two sources contribute scoped-property mappings for a given entity class:
 *   1. `#[Scoped]` PHP attributes declared directly on (or inherited by) the class.
 *   2. Programmatic registrations made via `ScopedFieldRegistry` (e.g. bridge module.php files).
 * Both sources are merged (union of axes, no duplicates) into the final `ScopeMetadata`.
 *
 * ## Lazy-scan side effect on ScopedFieldRegistry
 * On the *first* call to `for($class)` the factory performs a one-time reflection scan of
 * `$class` and its parent classes. Any `#[Scoped]` attribute findings are written back into
 * the `ScopedFieldRegistry` via `register()`, so that other consumers of the registry see the
 * full merged picture. Subsequent calls for the same class skip the scan entirely.
 *
 * ## Cache-staleness contract (frozen classes)
 * Once `for($class)` has been called and a `ScopeMetadata` instance has been cached, that
 * instance is frozen. Later mutations to `ScopedFieldRegistry` (e.g. a second `register()` call
 * for the same class) do NOT affect the already-cached result. Consumers that need to see all
 * registrations must ensure they call `register()` *before* the first `for()` for a given class.
 *
 * ## Empty-axes no-op rule
 * A `#[Scoped(axes: [])]` declaration on a property is treated as a no-op. The property will
 * NOT appear in the resulting `ScopeMetadata`. This differs from the old behaviour, which would
 * mark such a property as scoped-with-empty-axes.
 */
class ScopeMetadataFactory
{
    /**
     * @var array<class-string, ScopeMetadata>
     */
    private array $cache = [];

    /**
     * @var array<class-string, true>
     */
    private array $scanned = [];

    public function __construct(
        private readonly ScopeRegistryInterface $scopeRegistry,
        private readonly ScopedFieldRegistry $scopedFieldRegistry,
    ) {}

    /**
     * Build (or return cached) ScopeMetadata for the given class.
     *
     * On first access, performs a reflection scan of `$entityClass` (including inherited
     * properties) and writes any `#[Scoped]` findings into `ScopedFieldRegistry`. The scan
     * happens at most once per class, regardless of how many times `for()` is called.
     *
     * @param class-string $entityClass
     *
     * @throws UnknownAxisException
     */
    public function for(string $entityClass): ScopeMetadata
    {
        if (isset($this->cache[$entityClass])) {
            return $this->cache[$entityClass];
        }

        if (!isset($this->scanned[$entityClass])) {
            $this->scanAndRegister($entityClass);
            $this->scanned[$entityClass] = true;
        }

        $scopedProperties = $this->scopedFieldRegistry->propertiesFor($entityClass);
        $metadata = new ScopeMetadata($scopedProperties);
        $this->cache[$entityClass] = $metadata;

        return $metadata;
    }

    /**
     * Scan the entity class for #[Scoped] attributes and register findings into ScopedFieldRegistry.
     *
     * @param class-string $entityClass
     *
     * @throws UnknownAxisException
     */
    private function scanAndRegister(string $entityClass): void
    {
        $reflection = new ReflectionClass($entityClass);

        foreach ($reflection->getProperties() as $property) {
            $attributes = $property->getAttributes(Scoped::class);

            if (count($attributes) === 0) {
                continue;
            }

            $scoped = $attributes[0]->newInstance();
            $axes = $scoped->axes;

            if ($axes === []) {
                continue;
            }

            foreach ($axes as $axis) {
                if (!$this->scopeRegistry->hasAxis($axis)) {
                    throw UnknownAxisException::forAxis($axis);
                }
            }

            $this->scopedFieldRegistry->register(
                entityClass: $entityClass,
                property: $property->getName(),
                axes: $axes,
            );
        }
    }
}
