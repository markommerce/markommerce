<?php

declare(strict_types=1);

use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityCollection;
use Marko\Database\Repository\RepositoryQueryBuilder;
use Markommerce\Attribute\Contracts\AttributeDefinitionRepositoryInterface;
use Markommerce\Attribute\Entity\AttributeDefinition;
use Markommerce\Attribute\Entity\AttributeOption;
use Markommerce\AttributeScope\Entity\AttributeOptionScopedLabels;
use Markommerce\AttributeScope\ScopedOptionLabelResolver;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Resolution\ScopeWalker;
use Markommerce\Scope\Signature\SignatureCandidateEnumerator;

// ─── Helpers ───────────────────────────────────────────────────────────────────

/**
 * @param array<string, list<string>> $axesMap  axis name → scope paths
 * @param array<string, string> $defaults        axis name → default path
 */
function makeSolrRegistry(array $axesMap = [], array $defaults = []): ScopeRegistryInterface
{
    return new class ($axesMap, $defaults) implements ScopeRegistryInterface
    {
        /** @var array<string, ScopeAxis> */
        private array $builtAxes;

        /** @param array<string, list<string>> $axesMap @param array<string, string> $defaults */
        public function __construct(
            array $axesMap,
            array $defaults = [],
        )
        {
            $this->builtAxes = [];
            foreach ($axesMap as $name => $paths) {
                $default = $defaults[$name] ?? '__default';
                if (!in_array($default, $paths, true)) {
                    $paths = array_merge([$default], $paths);
                }
                $hierarchy = new ScopeHierarchy($paths);
                $this->builtAxes[$name] = new ScopeAxis(name: $name, hierarchy: $hierarchy, default: $default);
            }
        }

        public function hasAxis(string $name): bool
        {
            return isset($this->builtAxes[$name]);
        }

        /** @throws UnknownAxisException */
        public function getAxis(string $name): ScopeAxis
        {
            if (!isset($this->builtAxes[$name])) {
                throw UnknownAxisException::forAxis($name);
            }

            return $this->builtAxes[$name];
        }

        /** @return list<string> */
        public function listAxes(): array
        {
            return array_keys($this->builtAxes);
        }

        /** @throws UnknownAxisException */
        public function getHierarchy(string $axisName): ScopeHierarchy
        {
            return $this->getAxis($axisName)->hierarchy;
        }
    };
}

/**
 * Build a ScopedOptionLabelResolver with an in-memory repository seeded with one definition.
 *
 * @param array<string, list<string>> $axesMap
 * @param array<string, string> $axisDefaults
 * @param list<string> $definitionAxes  value for config()['axes'] on the definition
 * @param-out ScopeContext $outContext
 */
function makeSolr(
    array $axesMap = [],
    array $axisDefaults = [],
    array $definitionAxes = [],
    ?ScopeContext &$outContext = null,
): array {
    $registry = makeSolrRegistry($axesMap, $axisDefaults);
    $context  = new ScopeContext($registry);
    $outContext = $context;

    $enumerator = new SignatureCandidateEnumerator($registry);
    $walker     = new ScopeWalker($enumerator);

    $definition = new AttributeDefinition();
    $definition->id     = 1;
    $definition->config = ['axes' => $definitionAxes];

    $repository = new class ($definition) implements AttributeDefinitionRepositoryInterface
    {
        public function __construct(private AttributeDefinition $definition) {}

        public function find(int|string $id): ?AttributeDefinition
        {
            return $this->definition->id === $id ? $this->definition : null;
        }

        public function findOrFail(int|string $id): AttributeDefinition
        {
            return $this->find($id) ?? throw new RuntimeException("Not found: $id");
        }

        public function findAll(): EntityCollection
        {
            return new EntityCollection([$this->definition]);
        }

        /** @param array<string, mixed> $criteria */
        public function findBy(array $criteria): EntityCollection
        {
            return new EntityCollection([]);
        }

        /** @param array<string, mixed> $criteria */
        public function findOneBy(array $criteria): ?AttributeDefinition
        {
            return null;
        }

        /** @param array<string, mixed> $criteria */
        public function existsBy(array $criteria): bool
        {
            return false;
        }

        public function save(Entity $entity): void {}

        public function delete(Entity $entity): void {}

        /** @param array<Entity> $entities */
        public function insertBatch(array $entities): void {}

        public function findByCode(
            string $entityType,
            string $code,
        ): ?AttributeDefinition
        {
            return null;
        }

        /** @return list<AttributeOption> */
        public function optionsFor(AttributeDefinition $definition): array
        {
            return [];
        }

        public function saveOption(AttributeOption $option): void {}

        public function deleteOptionsFor(AttributeDefinition $definition): void {}

        public function query(): RepositoryQueryBuilder
        {
            throw new RuntimeException('Not supported');
        }
    };

    $resolver = new ScopedOptionLabelResolver(
        attributeDefinitionRepository: $repository,
        scopeWalker: $walker,
    );

    return [$resolver, $context];
}

// ─── Tests ──────────────────────────────────────────────────────────────────────

it('returns the base label when no scoped override matches the active scope', function (): void {
    /** @var ScopedOptionLabelResolver $resolver */
    /** @var ScopeContext $context */
    [$resolver, $context] = makeSolr(
        axesMap: ['locale' => ['en', 'fr']],
        definitionAxes: ['locale'],
    );

    $context->in('locale', 'en');

    $option = new AttributeOption();
    $option->id          = 10;
    $option->attributeId = 1;
    $option->label       = 'Base Label';

    $labels = new AttributeOptionScopedLabels();
    // No overrides set — should fall back to base label

    $result = $resolver->resolve($option, $labels, $context);

    expect($result)->toBe('Base Label');
});

it('returns the most-specific matching scoped label override', function (): void {
    /** @var ScopedOptionLabelResolver $resolver */
    /** @var ScopeContext $context */
    [$resolver, $context] = makeSolr(
        axesMap: ['locale' => ['en', 'fr']],
        definitionAxes: ['locale'],
    );

    $context->in('locale', 'en');

    $option = new AttributeOption();
    $option->id          = 10;
    $option->attributeId = 1;
    $option->label       = 'Base Label';

    $labels = new AttributeOptionScopedLabels();
    $labels->setOverride('locale:en', 'label', 'English Label');
    $labels->setOverride('locale:fr', 'label', 'French Label');

    $result = $resolver->resolve($option, $labels, $context);

    expect($result)->toBe('English Label');
});

it('falls back to the base label for an axis path with no override', function (): void {
    /** @var ScopedOptionLabelResolver $resolver */
    /** @var ScopeContext $context */
    [$resolver, $context] = makeSolr(
        axesMap: ['locale' => ['en', 'fr']],
        definitionAxes: ['locale'],
    );

    $context->in('locale', 'en'); // active scope is 'en' but only 'fr' has an override

    $option = new AttributeOption();
    $option->id          = 10;
    $option->attributeId = 1;
    $option->label       = 'Fallback Label';

    $labels = new AttributeOptionScopedLabels();
    $labels->setOverride('locale:fr', 'label', 'French Only Override');

    $result = $resolver->resolve($option, $labels, $context);

    expect($result)->toBe('Fallback Label');
});

it('resolves using the axes declared for the owning attribute', function (): void {
    // Build a registry with TWO axes: 'locale' and 'store'
    $registry = makeSolrRegistry(
        axesMap: ['locale' => ['en', 'fr'], 'store' => ['us', 'uk']],
    );
    $context = new ScopeContext($registry);

    $enumerator = new SignatureCandidateEnumerator($registry);
    $walker     = new ScopeWalker($enumerator);

    // Definition for attributeId=1 declares ONLY the 'locale' axis (not 'store')
    $localeDefinition = new AttributeDefinition();
    $localeDefinition->id     = 1;
    $localeDefinition->config = ['axes' => ['locale']];

    // Definition for attributeId=2 declares ONLY the 'store' axis
    $storeDefinition = new AttributeDefinition();
    $storeDefinition->id     = 2;
    $storeDefinition->config = ['axes' => ['store']];

    $repository = new class ($localeDefinition, $storeDefinition) implements AttributeDefinitionRepositoryInterface
    {
        public function __construct(
            private AttributeDefinition $localeDefinition,
            private AttributeDefinition $storeDefinition,
        ) {}

        public function find(int|string $id): ?AttributeDefinition
        {
            if ($this->localeDefinition->id === $id) {
                return $this->localeDefinition;
            }
            if ($this->storeDefinition->id === $id) {
                return $this->storeDefinition;
            }

            return null;
        }

        public function findOrFail(int|string $id): AttributeDefinition
        {
            return $this->find($id) ?? throw new RuntimeException("Not found: $id");
        }

        public function findAll(): EntityCollection
        {
            return new EntityCollection([]);
        }

        /** @param array<string, mixed> $criteria */
        public function findBy(array $criteria): EntityCollection
        {
            return new EntityCollection([]);
        }

        /** @param array<string, mixed> $criteria */
        public function findOneBy(array $criteria): ?AttributeDefinition
        {
            return null;
        }

        /** @param array<string, mixed> $criteria */
        public function existsBy(array $criteria): bool
        {
            return false;
        }

        public function save(Entity $entity): void {}

        public function delete(Entity $entity): void {}

        /** @param array<Entity> $entities */
        public function insertBatch(array $entities): void {}

        public function findByCode(
            string $entityType,
            string $code,
        ): ?AttributeDefinition
        {
            return null;
        }

        /** @return list<AttributeOption> */
        public function optionsFor(AttributeDefinition $definition): array
        {
            return [];
        }

        public function saveOption(AttributeOption $option): void {}

        public function deleteOptionsFor(AttributeDefinition $definition): void {}

        public function query(): RepositoryQueryBuilder
        {
            throw new RuntimeException('Not supported');
        }
    };

    $resolver = new ScopedOptionLabelResolver(
        attributeDefinitionRepository: $repository,
        scopeWalker: $walker,
    );

    // Active scopes: locale=en, store=us
    $context->in('locale', 'en');
    $context->in('store', 'us');

    // Option owned by attributeId=1 (locale axis only)
    $localeOption = new AttributeOption();
    $localeOption->id          = 10;
    $localeOption->attributeId = 1;
    $localeOption->label       = 'Locale Base';

    $localeLabels = new AttributeOptionScopedLabels();
    // Set locale:en override — should match because the attribute uses 'locale' axis
    $localeLabels->setOverride('locale:en', 'label', 'Locale EN Override');
    // Also set store:us — but this should NOT be consulted (attribute doesn't use 'store')
    $localeLabels->setOverride('store:us', 'label', 'Store US Override');

    $localeResult = $resolver->resolve($localeOption, $localeLabels, $context);

    // Option owned by attributeId=2 (store axis only)
    $storeOption = new AttributeOption();
    $storeOption->id          = 20;
    $storeOption->attributeId = 2;
    $storeOption->label       = 'Store Base';

    $storeLabels = new AttributeOptionScopedLabels();
    // Set store:us override — should match
    $storeLabels->setOverride('store:us', 'label', 'Store US Override');
    // locale:en is also set but should NOT be consulted (attribute uses 'store' axis only)
    $storeLabels->setOverride('locale:en', 'label', 'Locale EN Override');

    $storeResult = $resolver->resolve($storeOption, $storeLabels, $context);

    expect($localeResult)->toBe('Locale EN Override')
        ->and($storeResult)->toBe('Store US Override');
});
