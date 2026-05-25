<?php

declare(strict_types=1);

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Markommerce\Scope\Attributes\Scoped;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\InvalidSignatureForAttributeException;
use Markommerce\Scope\Exceptions\MultiAxisWalkAtNotSupportedException;
use Markommerce\Scope\Exceptions\ScopeContextException;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Metadata\ScopeMetadataFactory;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Resolution\ScopeWalker;
use Markommerce\Scope\Resolver\ScopeResolver;
use Markommerce\Scope\Signature\ScopeSignature;
use Markommerce\Scope\Signature\ScopeSignatureValidator;
use Markommerce\Scope\Signature\SignatureCandidateEnumerator;
use Markommerce\Scope\Storage\HasScopes;
use Markommerce\Scope\Storage\HasScopesInterface;

// ─── Fixtures ────────────────────────────────────────────────────────────────

#[Table(name: 'resolver_products')]
class ResolverProduct extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Scoped(axes: ['store'])]
    #[Column]
    public string $name = 'default-name';

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public string $sku = 'default-sku';
}

#[Table(name: 'trait_resolver_products')]
class TraitResolverProduct extends Entity implements HasScopesInterface
{
    use HasScopes;

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Scoped(axes: ['store'])]
    #[Column]
    public string $name = 'default-name';

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public string $sku = 'default-sku';
}

#[Table(extends: ResolverProduct::class)]
class ManualCompanionProduct extends Entity implements HasScopesInterface
{
    use HasScopes;
}

#[Table(name: 'two_axis_products')]
class TwoAxisProduct extends Entity implements HasScopesInterface
{
    use HasScopes;

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Scoped(axes: ['store', 'currency'])]
    #[Column]
    public string $price = '0.00';
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * @param array<string, list<string>> $axes
 * @param array<string, string> $defaults
 */
function makeResolverRegistry(array $axes = ['store' => ['global', 'global.us']], array $defaults = []): ScopeRegistryInterface
{
    return new class ($axes, $defaults) implements ScopeRegistryInterface
    {
        /** @var array<string, ScopeAxis> */
        private array $builtAxes;

        /** @param array<string, list<string>> $axes @param array<string, string> $defaults */
        public function __construct(
            array $axes,
            array $defaults = [],
        )
        {
            $this->builtAxes = [];
            foreach ($axes as $name => $paths) {
                $default = $defaults[$name] ?? '__test_default';
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

function makeResolverSetup(): array
{
    $registry = makeResolverRegistry();
    $context = new ScopeContext($registry);
    $scopeMetaFactory = new ScopeMetadataFactory($registry);
    $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));
    $validator = new ScopeSignatureValidator($registry);

    return [$registry, $context, $scopeMetaFactory, $walker, $validator];
}

function makeResolver(): array
{
    [$registry, $context, $scopeMetaFactory, $walker, $validator] = makeResolverSetup();
    $resolver = new ScopeResolver($scopeMetaFactory, $walker, $context, $validator);

    return [$resolver, $context, $registry];
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('resolves a property value via current ScopeContext returning the walker match', function (): void {
    [$resolver, $context] = makeResolver();
    $context->in('store', 'global.us');

    $product = new ResolverProduct();
    $companion = new ManualCompanionProduct();
    $companion->setOverride('store:global.us', 'name', 'US Name');
    $product->attachCompanion($companion);

    $result = $resolver->resolved($product, 'name');

    expect($result)->toBe('US Name');
});

it('resolves at an explicit scope via resolvedAt without consulting ScopeContext', function (): void {
    [$resolver, $context] = makeResolver();
    // context set to global.us, but we resolve at global explicitly
    $context->in('store', 'global.us');

    $product = new ResolverProduct();
    $companion = new ManualCompanionProduct();
    $companion->setOverride('store:global', 'name', 'Global Name');
    $companion->setOverride('store:global.us', 'name', 'US Name');
    $product->attachCompanion($companion);

    $scope = new ScopeSignature(['store' => 'global']);
    $result = $resolver->resolvedAt($product, 'name', $scope);

    expect($result)->toBe('Global Name');
});

it('clears an override via clearOverride leaving the companion otherwise intact', function (): void {
    [$resolver] = makeResolver();

    $product = new ResolverProduct();
    $companion = new ManualCompanionProduct();
    $companion->setOverride('store:global.us', 'name', 'US Name');
    $companion->setOverride('store:global.us', 'sku', 'SKU-US');
    $product->attachCompanion($companion);

    $scope = new ScopeSignature(['store' => 'global.us']);
    $resolver->clearOverride($product, 'name', $scope);

    expect($companion->hasOverride('store:global.us', 'name'))->toBeFalse()
        ->and($companion->override('store:global.us', 'sku'))->toBe('SKU-US');
});

it('throws ScopeContextException when setOverride targets a property without Scoped', function (): void {
    [$resolver] = makeResolver();

    $product = new ResolverProduct();
    // 'sku' is not marked with #[Scoped]
    $scope = new ScopeSignature(['store' => 'global.us']);

    expect(fn () => $resolver->setOverride($product, 'sku', 'SKU-123', $scope))
        ->toThrow(ScopeContextException::class);
});

it('throws ScopeContextException when resolving an unknown property', function (): void {
    [$resolver, $context] = makeResolver();
    $context->in('store', 'global.us');

    $product = new ResolverProduct();

    expect(fn () => $resolver->resolved($product, 'nonExistentProperty'))
        ->toThrow(ScopeContextException::class);
});

it('falls back to the entity\'s column property value when no override is found', function (): void {
    [$resolver, $context] = makeResolver();
    $context->in('store', 'global.us');

    $product = new ResolverProduct();
    $product->name = 'base-name';
    $companion = new ManualCompanionProduct();
    // No override set for 'name'
    $product->attachCompanion($companion);

    $result = $resolver->resolved($product, 'name');

    expect($result)->toBe('base-name');
});

it('resolves a scoped value when the entity itself implements HasScopesInterface', function (): void {
    [$resolver, $context] = makeResolver();
    $context->in('store', 'global.us');

    $product = new TraitResolverProduct();
    $product->setOverride('store:global.us', 'name', 'Trait US Name');

    $result = $resolver->resolved($product, 'name');

    expect($result)->toBe('Trait US Name');
});

it('falls back to the column value when entity implements HasScopesInterface but has no override', function (): void {
    [$resolver, $context] = makeResolver();
    $context->in('store', 'global.us');

    $product = new TraitResolverProduct();
    $product->name = 'base-trait-name';

    $result = $resolver->resolved($product, 'name');

    expect($result)->toBe('base-trait-name');
});

it(
    'sets an override directly on the entity when it implements HasScopesInterface and no companion is attached or created',
    function (): void {
        [$resolver] = makeResolver();

        $product = new TraitResolverProduct();

        $scope = new ScopeSignature(['store' => 'global.us']);
        $resolver->setOverride($product, 'name', 'Direct Override', $scope);

        expect($product->override('store:global.us', 'name'))->toBe('Direct Override')
            ->and($product->companions())->toBeEmpty();
    },
);

it('clears an override directly on the entity when it implements HasScopesInterface', function (): void {
    [$resolver] = makeResolver();

    $product = new TraitResolverProduct();
    $product->setOverride('store:global.us', 'name', 'To Be Cleared');

    $scope = new ScopeSignature(['store' => 'global.us']);
    $resolver->clearOverride($product, 'name', $scope);

    expect($product->hasOverride('store:global.us', 'name'))->toBeFalse();
});

it(
    'silently no-ops when clearOverride is called on a trait-based entity that has no overrides yet',
    function (): void {
        [$resolver] = makeResolver();

        $product = new TraitResolverProduct();

        $scope = new ScopeSignature(['store' => 'global.us']);

        // Should not throw
        $resolver->clearOverride($product, 'name', $scope);

        expect($product->scopes)->toBeNull();
    },
);

it(
    'throws ScopeContextException when setOverride is called on an entity that does not implement HasScopesInterface and has no companion registered as an extender',
    function (): void {
        // Build a resolver with NO extenders registered for ResolverProduct
        $registry = makeResolverRegistry();
        $context = new ScopeContext($registry);
        $scopeMetaFactory = new ScopeMetadataFactory($registry);
        $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));
        $validator = new ScopeSignatureValidator($registry);
        $resolver = new ScopeResolver($scopeMetaFactory, $walker, $context, $validator);

        $product = new ResolverProduct();
        $scope = new ScopeSignature(['store' => 'global.us']);

        expect(fn () => $resolver->setOverride($product, 'name', 'Name', $scope))
            ->toThrow(ScopeContextException::class);
    },
);

it('resolvedAt returns the correct value for an explicit scope on a trait-based entity', function (): void {
    [$resolver, $context] = makeResolver();
    $context->in('store', 'global.us');

    $product = new TraitResolverProduct();
    $product->setOverride('store:global', 'name', 'Global Name');
    $product->setOverride('store:global.us', 'name', 'US Name');

    $scope = new ScopeSignature(['store' => 'global']);
    $result = $resolver->resolvedAt($product, 'name', $scope);

    expect($result)->toBe('Global Name');
});

it(
    'returns the column value when resolvedAt finds no override for the given scope on a trait-based entity',
    function (): void {
        [$resolver] = makeResolver();

        $product = new TraitResolverProduct();
        $product->name = 'column-value';

        $scope = new ScopeSignature(['store' => 'global.us']);
        $result = $resolver->resolvedAt($product, 'name', $scope);

        expect($result)->toBe('column-value');
    },
);

it(
    'prefers the entity itself over any attached companion when both implement HasScopesInterface (entity-self wins ordering)',
    function (): void {
        [$resolver, $context] = makeResolver();
        $context->in('store', 'global.us');

        $product = new TraitResolverProduct();
        $product->setOverride('store:global.us', 'name', 'Entity Override');

        // Attach a companion that also has an override — entity should win
        $companion = new ManualCompanionProduct();
        $companion->setOverride('store:global.us', 'name', 'Companion Override');
        $product->attachCompanion($companion);

        $result = $resolver->resolved($product, 'name');

        expect($result)->toBe('Entity Override');
    },
);

it('does not create or attach a companion when setOverride is called on a trait-based entity', function (): void {
    [$resolver] = makeResolver();

    $product = new TraitResolverProduct();

    $scope = new ScopeSignature(['store' => 'global.us']);
    $resolver->setOverride($product, 'name', 'Direct', $scope);

    expect($product->companions())->toBeEmpty();
});

it(
    'ScopeResolver setOverride throws ScopeContextException when entity has no HasScopesInterface and no compatible companion',
    function (): void {
        $registry = makeResolverRegistry();
        $context = new ScopeContext($registry);
        $scopeMetaFactory = new ScopeMetadataFactory($registry);
        $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));
        $validator = new ScopeSignatureValidator($registry);
        $resolver = new ScopeResolver($scopeMetaFactory, $walker, $context, $validator);

        $product = new ResolverProduct();
        $scope = new ScopeSignature(['store' => 'global.us']);

        expect(fn () => $resolver->setOverride($product, 'name', 'Name', $scope))
            ->toThrow(ScopeContextException::class);
    },
);

it('ScopeResolver setOverride works on a trait-based entity', function (): void {
    $registry = makeResolverRegistry();
    $context = new ScopeContext($registry);
    $scopeMetaFactory = new ScopeMetadataFactory($registry);
    $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));
    $validator = new ScopeSignatureValidator($registry);
    $resolver = new ScopeResolver($scopeMetaFactory, $walker, $context, $validator);

    $product = new TraitResolverProduct();
    $scope = new ScopeSignature(['store' => 'global.us']);
    $resolver->setOverride($product, 'name', 'Trait Override', $scope);

    expect($product->override('store:global.us', 'name'))->toBe('Trait Override')
        ->and($product->companions())->toBeEmpty();
});

it('ScopeResolver setOverride works when a manual HasScopesInterface companion is attached', function (): void {
    $registry = makeResolverRegistry();
    $context = new ScopeContext($registry);
    $scopeMetaFactory = new ScopeMetadataFactory($registry);
    $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));
    $validator = new ScopeSignatureValidator($registry);
    $resolver = new ScopeResolver($scopeMetaFactory, $walker, $context, $validator);

    $product = new ResolverProduct();
    $companion = new ManualCompanionProduct();
    $product->attachCompanion($companion);
    $scope = new ScopeSignature(['store' => 'global.us']);
    $resolver->setOverride($product, 'name', 'Companion Override', $scope);

    expect($companion->override('store:global.us', 'name'))->toBe('Companion Override');
});

it(
    'ScopeResolver resolved works with a manual HasScopesInterface companion (not ScopedOverridesEntity)',
    function (): void {
        $registry = makeResolverRegistry();
        $context = new ScopeContext($registry);
        $context->in('store', 'global.us');
        $scopeMetaFactory = new ScopeMetadataFactory($registry);
        $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));
        $validator = new ScopeSignatureValidator($registry);
        $resolver = new ScopeResolver($scopeMetaFactory, $walker, $context, $validator);

        $product = new ResolverProduct();
        $companion = new ManualCompanionProduct();
        $companion->setOverride('store:global.us', 'name', 'Manual Companion Name');
        $product->attachCompanion($companion);

        $result = $resolver->resolved($product, 'name');

        expect($result)->toBe('Manual Companion Name');
    },
);

it('ScopeResolver does not have a createCompanion method', function (): void {
    $reflection = new ReflectionClass(ScopeResolver::class);

    expect($reflection->hasMethod('createCompanion'))->toBeFalse();
});

it('ScopeResolver::resolvedAt accepts a ScopeSignature parameter', function (): void {
    $reflection = new ReflectionClass(ScopeResolver::class);
    $method = $reflection->getMethod('resolvedAt');
    $params = $method->getParameters();

    $signatureParam = array_find($params, fn (ReflectionParameter $p) => $p->getName() === 'signature');

    expect($signatureParam)->not->toBeNull();
    expect($signatureParam->getType()?->getName())->toBe(ScopeSignature::class);
});

it('ScopeResolver::setOverride accepts a ScopeSignature parameter', function (): void {
    $reflection = new ReflectionClass(ScopeResolver::class);
    $method = $reflection->getMethod('setOverride');
    $params = $method->getParameters();

    $signatureParam = array_find($params, fn (ReflectionParameter $p) => $p->getName() === 'signature');

    expect($signatureParam)->not->toBeNull();
    expect($signatureParam->getType()?->getName())->toBe(ScopeSignature::class);
});

it('ScopeResolver::clearOverride accepts a ScopeSignature parameter', function (): void {
    $reflection = new ReflectionClass(ScopeResolver::class);
    $method = $reflection->getMethod('clearOverride');
    $params = $method->getParameters();

    $signatureParam = array_find($params, fn (ReflectionParameter $p) => $p->getName() === 'signature');

    expect($signatureParam)->not->toBeNull();
    expect($signatureParam->getType()?->getName())->toBe(ScopeSignature::class);
});

it('setOverride writes the value to storage under the signature\'s canonical string key', function (): void {
    [$resolver] = makeResolver();

    $product = new TraitResolverProduct();
    $scope = new ScopeSignature(['store' => 'global.us']);
    $resolver->setOverride($product, 'name', 'Written Value', $scope);

    expect($product->override('store:global.us', 'name'))->toBe('Written Value');
});

it(
    'setOverride throws InvalidSignatureForAttributeException when the signature mentions an axis not in the attribute axes (case 16)',
    function (): void {
        [$resolver] = makeResolver();

        $product = new TraitResolverProduct();
        // 'name' has only 'store' axis; 'website' is not a declared axis for it
        $scope = new ScopeSignature(['website' => 'global.us']);

        expect(fn () => $resolver->setOverride($product, 'name', 'Value', $scope))
            ->toThrow(InvalidSignatureForAttributeException::class);
    },
);

it(
    'setOverride throws InvalidSignatureForAttributeException when the signature value is not in the registry hierarchy (case 17)',
    function (): void {
        [$resolver] = makeResolver();

        $product = new TraitResolverProduct();
        // 'name' has 'store' axis; 'nonexistent.node' is not in the hierarchy
        $scope = new ScopeSignature(['store' => 'nonexistent.node']);

        expect(fn () => $resolver->setOverride($product, 'name', 'Value', $scope))
            ->toThrow(InvalidSignatureForAttributeException::class);
    },
);

it('setOverride accepts a single-axis signature for a single-axis attribute', function (): void {
    [$resolver] = makeResolver();

    $product = new TraitResolverProduct();
    $scope = new ScopeSignature(['store' => 'global.us']);

    // Should not throw
    $resolver->setOverride($product, 'name', 'Valid Value', $scope);

    expect($product->override('store:global.us', 'name'))->toBe('Valid Value');
});

it('setOverride accepts a two-axis composite signature for a two-axis attribute', function (): void {
    $registry = makeResolverRegistry([
        'store' => ['global', 'global.us'],
        'currency' => ['usd', 'eur'],
    ]);
    $context = new ScopeContext($registry);
    $scopeMetaFactory = new ScopeMetadataFactory($registry);
    $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));
    $validator = new ScopeSignatureValidator($registry);
    $resolver = new ScopeResolver($scopeMetaFactory, $walker, $context, $validator);

    $product = new TwoAxisProduct();
    $scope = new ScopeSignature(['store' => 'global.us', 'currency' => 'usd']);

    // Should not throw
    $resolver->setOverride($product, 'price', '9.99', $scope);

    expect($product->override('currency:usd|store:global.us', 'price'))->toBe('9.99');
});

it('setOverride accepts a partial signature (subset of the attribute axes)', function (): void {
    $registry = makeResolverRegistry([
        'store' => ['global', 'global.us'],
        'currency' => ['usd', 'eur'],
    ]);
    $context = new ScopeContext($registry);
    $scopeMetaFactory = new ScopeMetadataFactory($registry);
    $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));
    $validator = new ScopeSignatureValidator($registry);
    $resolver = new ScopeResolver($scopeMetaFactory, $walker, $context, $validator);

    $product = new TwoAxisProduct();
    // Only 'store' axis, which is a subset of ['store', 'currency']
    $scope = new ScopeSignature(['store' => 'global.us']);

    // Should not throw
    $resolver->setOverride($product, 'price', '7.99', $scope);

    expect($product->override('store:global.us', 'price'))->toBe('7.99');
});

it('clearOverride removes the entry under the signature\'s canonical string key', function (): void {
    [$resolver] = makeResolver();

    $product = new TraitResolverProduct();
    $product->setOverride('store:global.us', 'name', 'To Remove');

    $scope = new ScopeSignature(['store' => 'global.us']);
    $resolver->clearOverride($product, 'name', $scope);

    expect($product->hasOverride('store:global.us', 'name'))->toBeFalse();
});

it('clearOverride throws InvalidSignatureForAttributeException for an invalid signature', function (): void {
    [$resolver] = makeResolver();

    $product = new TraitResolverProduct();
    // 'website' is not a declared axis for 'name'
    $scope = new ScopeSignature(['website' => 'global.us']);

    expect(fn () => $resolver->clearOverride($product, 'name', $scope))
        ->toThrow(InvalidSignatureForAttributeException::class);
});

it('resolved continues to use the active ScopeContext without requiring a signature', function (): void {
    [$resolver, $context] = makeResolver();
    $context->in('store', 'global.us');

    $product = new TraitResolverProduct();
    $product->setOverride('store:global.us', 'name', 'Context-Resolved Name');

    $result = $resolver->resolved($product, 'name');

    expect($result)->toBe('Context-Resolved Name');
});

it('resolvedAt delegates to walkAt which throws on multi-axis signatures', function (): void {
    $registry = makeResolverRegistry([
        'store' => ['global', 'global.us'],
        'currency' => ['usd', 'eur'],
    ]);
    $context = new ScopeContext($registry);
    $scopeMetaFactory = new ScopeMetadataFactory($registry);
    $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));
    $validator = new ScopeSignatureValidator($registry);
    $resolver = new ScopeResolver($scopeMetaFactory, $walker, $context, $validator);

    $product = new TwoAxisProduct();
    // Multi-axis signature — walkAt does not support these
    $scope = new ScopeSignature(['store' => 'global.us', 'currency' => 'usd']);

    expect(fn () => $resolver->resolvedAt($product, 'price', $scope))
        ->toThrow(MultiAxisWalkAtNotSupportedException::class);
});

it(
    'the resolver does not call the validator on the read (resolved / resolvedAt) path (verified via instrumented validator)',
    function (): void {
        $registry = makeResolverRegistry();
        $context = new ScopeContext($registry);
        $context->in('store', 'global.us');
        $scopeMetaFactory = new ScopeMetadataFactory($registry);
        $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));

        $callCount = 0;
        $instrumentedValidator = new class ($registry, $callCount) extends ScopeSignatureValidator
        {
            public function __construct(
                ScopeRegistryInterface $scopeRegistry,
                private int &$callCount,
            ) {
                parent::__construct($scopeRegistry);
            }

            public function validate(
                ScopeSignature $scopeSignature,
                array $attributeAxes,
            ): void {
                $this->callCount++;
                parent::validate($scopeSignature, $attributeAxes);
            }
        };

        $resolver = new ScopeResolver($scopeMetaFactory, $walker, $context, $instrumentedValidator);

        $product = new TraitResolverProduct();
        $product->setOverride('store:global.us', 'name', 'Read Value');

        // Call both read paths
        $resolver->resolved($product, 'name');
        $resolver->resolvedAt($product, 'name', new ScopeSignature(['store' => 'global.us']));

        expect($callCount)->toBe(0);
    },
);
