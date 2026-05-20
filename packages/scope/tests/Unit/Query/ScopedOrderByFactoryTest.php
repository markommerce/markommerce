<?php

declare(strict_types=1);

use Markommerce\Scope\Attributes\Scoped;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Metadata\ScopeMetadataFactory;
use Markommerce\Scope\Query\ScopedFieldExpression;
use Markommerce\Scope\Query\ScopedFieldRendererInterface;
use Markommerce\Scope\Query\ScopedOrderBy;
use Markommerce\Scope\Query\ScopedOrderByFactory;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Signature\SignatureCandidateEnumerator;

// ─── Fixtures ────────────────────────────────────────────────────────────────

class ScopedOrderByFactoryProduct
{
    #[Scoped(axes: ['store'])]
    public string $name = '';
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function makeFactoryRegistry(): ScopeRegistryInterface
{
    return new class () implements ScopeRegistryInterface
    {
        private array $builtAxes;

        public function __construct()
        {
            $hierarchy = new ScopeHierarchy(['__test_default', 'en', 'en.gb']);
            $this->builtAxes = ['store' => new ScopeAxis(name: 'store', hierarchy: $hierarchy, default: '__test_default')];
        }

        public function hasAxis(string $name): bool
        {
            return isset($this->builtAxes[$name]);
        }

        public function getAxis(string $name): ScopeAxis
        {
            return $this->builtAxes[$name];
        }

        public function listAxes(): array
        {
            return array_keys($this->builtAxes);
        }

        public function getHierarchy(string $axisName): ScopeHierarchy
        {
            return $this->builtAxes[$axisName]->hierarchy;
        }
    };
}

function makeFactoryRenderer(): ScopedFieldRendererInterface
{
    return new class () implements ScopedFieldRendererInterface
    {
        public function render(ScopedFieldExpression $expression): string
        {
            return 'COALESCE(expr)';
        }
    };
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('constructs a ScopedOrderBy with the entity class, property, and direction', function (): void {
    $registry = makeFactoryRegistry();
    $metadataFactory = new ScopeMetadataFactory($registry);
    $context = new ScopeContext($registry);
    $renderer = makeFactoryRenderer();
    $enumerator = new SignatureCandidateEnumerator($registry);

    $factory = new ScopedOrderByFactory($metadataFactory, $context, $renderer, $enumerator);
    $spec = $factory->create(ScopedOrderByFactoryProduct::class, 'name', 'desc');

    expect($spec)->toBeInstanceOf(ScopedOrderBy::class)
        ->and($spec->property)->toBe('name')
        ->and($spec->direction)->toBe('desc');
});

it('injects ScopeMetadataFactory, ScopeContext, and ScopedFieldRendererInterface into the spec', function (): void {
    $registry = makeFactoryRegistry();
    $metadataFactory = new ScopeMetadataFactory($registry);
    $context = new ScopeContext($registry);
    $renderer = makeFactoryRenderer();
    $enumerator = new SignatureCandidateEnumerator($registry);

    $factory = new ScopedOrderByFactory($metadataFactory, $context, $renderer, $enumerator);
    $spec = $factory->create(ScopedOrderByFactoryProduct::class, 'name', 'asc');

    expect($spec)->toBeInstanceOf(ScopedOrderBy::class);
});

it('defaults direction to asc when omitted', function (): void {
    $registry = makeFactoryRegistry();
    $metadataFactory = new ScopeMetadataFactory($registry);
    $context = new ScopeContext($registry);
    $renderer = makeFactoryRenderer();
    $enumerator = new SignatureCandidateEnumerator($registry);

    $factory = new ScopedOrderByFactory($metadataFactory, $context, $renderer, $enumerator);
    $spec = $factory->create(ScopedOrderByFactoryProduct::class, 'name');

    expect($spec->direction)->toBe('asc');
});

it('is a readonly class with constructor-injected dependencies', function (): void {
    $reflection = new ReflectionClass(ScopedOrderByFactory::class);

    expect($reflection->isReadOnly())->toBeTrue();
});

it('it constructs a ScopedOrderBy with the new enumerator and field renderer dependencies', function (): void {
    $registry = makeFactoryRegistry();
    $metadataFactory = new ScopeMetadataFactory($registry);
    $context = new ScopeContext($registry);
    $renderer = makeFactoryRenderer();
    $enumerator = new SignatureCandidateEnumerator($registry);

    $factory = new ScopedOrderByFactory($metadataFactory, $context, $renderer, $enumerator);

    // Verify the factory accepts all four dependencies and creates a ScopedOrderBy
    $spec = $factory->create(ScopedOrderByFactoryProduct::class, 'name', 'asc');

    // Reflect on the spec to verify enumerator and renderer are injected
    $reflection = new ReflectionClass($spec);
    $enumProp = $reflection->getProperty('signatureCandidateEnumerator');
    $rendererProp = $reflection->getProperty('scopedFieldRenderer');

    expect($enumProp->getValue($spec))->toBe($enumerator)
        ->and($rendererProp->getValue($spec))->toBe($renderer);
});

it('it forwards the entity class, property, and direction to the spec', function (): void {
    $registry = makeFactoryRegistry();
    $metadataFactory = new ScopeMetadataFactory($registry);
    $context = new ScopeContext($registry);
    $renderer = makeFactoryRenderer();
    $enumerator = new SignatureCandidateEnumerator($registry);

    $factory = new ScopedOrderByFactory($metadataFactory, $context, $renderer, $enumerator);
    $spec = $factory->create(ScopedOrderByFactoryProduct::class, 'name', 'desc');

    $reflection = new ReflectionClass($spec);
    $entityClassProp = $reflection->getProperty('entityClass');

    expect($spec->property)->toBe('name')
        ->and($spec->direction)->toBe('desc')
        ->and($entityClassProp->getValue($spec))->toBe(ScopedOrderByFactoryProduct::class);
});
