<?php

declare(strict_types=1);

use Marko\Config\ConfigMerger;
use Marko\Config\ConfigRepository;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Marko\Database\Query\EntityQueryBuilderInterface;
use Marko\Database\Query\QueryBuilderInterface;
use Markommerce\Scope\Attributes\Scoped;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\InvalidSignatureForAttributeException;
use Markommerce\Scope\Metadata\ScopeMetadataFactory;
use Markommerce\Scope\Query\ScopedFieldExpression;
use Markommerce\Scope\Query\ScopedFieldRendererInterface;
use Markommerce\Scope\Query\ScopedOrderBy;
use Markommerce\Scope\Registry\PhpScopeRegistry;
use Markommerce\Scope\Resolution\ScopeWalker;
use Markommerce\Scope\Resolver\ScopeResolver;
use Markommerce\Scope\Signature\ScopeSignature;
use Markommerce\Scope\Signature\ScopeSignatureValidator;
use Markommerce\Scope\Signature\SignatureCandidateEnumerator;
use Markommerce\Scope\Storage\DefaultScopeGuard;
use Markommerce\Scope\Storage\HasScopes;
use Markommerce\Scope\Storage\HasScopesInterface;

// ─── Fixtures ─────────────────────────────────────────────────────────────────

#[Table(name: 'default_scope_products')]
class DefaultScopeProduct extends Entity implements HasScopesInterface
{
    use HasScopes;

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Scoped(axes: ['locale', 'channel'])]
    #[Column]
    public string $name = 'base-name';
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Load the shipped scope config and build a real PhpScopeRegistry from it.
 */
function loadShippedRegistry(): PhpScopeRegistry
{
    $rawConfig = require dirname(__DIR__, 2) . '/config/scope.php';
    $config = new ConfigRepository(['scope' => $rawConfig]);

    return new PhpScopeRegistry($config);
}

/**
 * Build the full resolver stack from a given registry.
 */
function buildResolverStack(PhpScopeRegistry $registry): array
{
    $context = new ScopeContext($registry);
    $metadataFactory = new ScopeMetadataFactory($registry);
    $enumerator = new SignatureCandidateEnumerator($registry);
    $walker = new ScopeWalker($enumerator);
    $validator = new ScopeSignatureValidator($registry);
    $resolver = new ScopeResolver($metadataFactory, $walker, $context, $validator);

    return [$registry, $context, $metadataFactory, $enumerator, $walker, $validator, $resolver];
}

/**
 * A minimal recording fake for EntityQueryBuilderInterface.
 * Tracks which orderBy / orderByRaw calls were made.
 */
function makeRecordingBuilder(): EntityQueryBuilderInterface
{
    return new class () implements EntityQueryBuilderInterface
    {
        /** @var list<array{column: string, direction: string}> */
        public array $orderByCalls = [];

        /** @var list<array{expression: string, direction: string}> */
        public array $orderByRawCalls = [];

        public function with(string ...$relations): static
        {
            return $this;
        }

        public function table(string $table): static
        {
            return $this;
        }

        public function select(string ...$columns): static
        {
            return $this;
        }

        public function selectRaw(string $expression, array $bindings = []): static
        {
            return $this;
        }

        public function distinct(): static
        {
            return $this;
        }

        public function where(string $column, string $operator, mixed $value): static
        {
            return $this;
        }

        public function whereIn(string $column, array $values): static
        {
            return $this;
        }

        public function whereNull(string $column): static
        {
            return $this;
        }

        public function whereNotNull(string $column): static
        {
            return $this;
        }

        public function whereJsonContains(string $column, mixed $value): static
        {
            return $this;
        }

        public function whereJsonExists(string $path): static
        {
            return $this;
        }

        public function whereJsonMissing(string $path): static
        {
            return $this;
        }

        public function whereRaw(string $expression, array $bindings = []): static
        {
            return $this;
        }

        public function orWhere(string $column, string $operator, mixed $value): static
        {
            return $this;
        }

        public function join(string $table, string $first, string $operator, string $second): static
        {
            return $this;
        }

        public function leftJoin(string $table, string $first, string $operator, string $second): static
        {
            return $this;
        }

        public function rightJoin(string $table, string $first, string $operator, string $second): static
        {
            return $this;
        }

        public function groupBy(string ...$columns): static
        {
            return $this;
        }

        public function having(string $expression, array $bindings = []): static
        {
            return $this;
        }

        public function union(QueryBuilderInterface $other): static
        {
            return $this;
        }

        public function unionAll(QueryBuilderInterface $other): static
        {
            return $this;
        }

        public function limit(int $limit): static
        {
            return $this;
        }

        public function offset(int $offset): static
        {
            return $this;
        }

        public function getColumnCount(): int
        {
            return 0;
        }

        public function compileSubquery(array &$bindings): string
        {
            return '';
        }

        public function get(): array
        {
            return [];
        }

        public function first(): ?array
        {
            return null;
        }

        public function insert(array $data): int
        {
            return 0;
        }

        public function update(array $data): int
        {
            return 0;
        }

        public function delete(): int
        {
            return 0;
        }

        public function count(?string $column = null): int
        {
            return 0;
        }

        public function min(string $column): int|float|null
        {
            return null;
        }

        public function max(string $column): int|float|null
        {
            return null;
        }

        public function sum(string $column): int|float|null
        {
            return null;
        }

        public function avg(string $column): int|float|null
        {
            return null;
        }

        public function raw(string $sql, array $bindings = []): array
        {
            return [];
        }

        public function orderBy(string $column, string $direction = 'ASC'): static
        {
            $this->orderByCalls[] = ['column' => $column, 'direction' => $direction];

            return $this;
        }

        public function orderByRaw(string $expression, string $direction = 'ASC'): static
        {
            $this->orderByRawCalls[] = ['expression' => $expression, 'direction' => $direction];

            return $this;
        }
    };
}

/**
 * A stub renderer that never renders (should not be called in all-default context).
 */
function makeNullRenderer(): ScopedFieldRendererInterface
{
    return new readonly class () implements ScopedFieldRendererInterface
    {
        public function render(ScopedFieldExpression $expression): string
        {
            return '';
        }
    };
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('resolves a scoped property to the base column value when the context is entirely at defaults', function (): void {
    DefaultScopeGuard::reset();

    $registry = loadShippedRegistry();
    [, $context, , , , , $resolver] = buildResolverStack($registry);

    // Leave context empty — no axis is set, which represents an all-default context.
    // The SignatureCandidateEnumerator will produce zero candidates, so the walker
    // finds no override and resolved() falls back to the base column property.
    $product = new DefaultScopeProduct();
    $product->name = 'base-name';

    $result = $resolver->resolved($product, 'name');

    expect($result)->toBe('base-name');
});

it('produces a plain order by clause without a scopes json lookup for an all-default context', function (): void {
    DefaultScopeGuard::reset();

    $registry = loadShippedRegistry();
    [, $context, $metadataFactory, $enumerator] = buildResolverStack($registry);

    // All-default context: no axis is active in ScopeContext.
    // SignatureCandidateEnumerator produces zero candidates → ScopedOrderBy takes
    // the plain orderBy() branch, not the orderByRaw() / scopes-JSON branch.
    $renderer = makeNullRenderer();
    $spec = new ScopedOrderBy(
        property: 'name',
        scopeMetadataFactory: $metadataFactory,
        scopeContext: $context,
        scopedFieldRenderer: $renderer,
        signatureCandidateEnumerator: $enumerator,
        entityClass: DefaultScopeProduct::class,
    );

    $builder = makeRecordingBuilder();
    $spec->apply($builder);

    expect($builder->orderByCalls)->toHaveCount(1)
        ->and($builder->orderByCalls[0]['column'])->toBe('name')
        ->and($builder->orderByRawCalls)->toBeEmpty();
});

it('resolves overrides at non-default scopes added after the registry was extended', function (): void {
    DefaultScopeGuard::reset();

    // Load the shipped config and deep-merge an extension that adds 'en' to locale scopes.
    $rawBase = require dirname(__DIR__, 2) . '/config/scope.php';
    $extension = ['axes' => ['locale' => ['scopes' => ['en' => []]]]];
    $merged = (new ConfigMerger())->merge($rawBase, $extension);
    $config = new ConfigRepository(['scope' => $merged]);
    $registry = new PhpScopeRegistry($config);

    // Configure DefaultScopeGuard from the extended registry so that
    // locale:default is blocked but locale:en is writable.
    $defaults = [];
    foreach ($registry->listAxes() as $axisName) {
        $defaults[$axisName] = $registry->getAxis($axisName)->default;
    }
    DefaultScopeGuard::configure($defaults);

    [, $context, , , , , $resolver] = buildResolverStack($registry);

    // Set the context to locale:en (a non-default scope now available in the extended registry).
    $context->in('locale', 'en');

    $product = new DefaultScopeProduct();
    $product->name = 'base-name';

    // Write an override directly at locale:en on the entity's HasScopes storage.
    $product->setOverride('locale:en', 'name', 'English Name');

    $result = $resolver->resolved($product, 'name');

    expect($result)->toBe('English Name');
});

it('rejects setOverride at a default scope through ScopeResolver', function (): void {
    DefaultScopeGuard::reset();

    $registry = loadShippedRegistry();
    [, , , , , , $resolver] = buildResolverStack($registry);

    $product = new DefaultScopeProduct();

    // locale axis has default='default'; attempting to write an override at
    // locale:default must be rejected by ScopeSignatureValidator before it
    // even reaches the storage layer.
    $defaultSignature = new ScopeSignature(['locale' => 'default']);

    expect(fn () => $resolver->setOverride($product, 'name', 'Should Fail', $defaultSignature))
        ->toThrow(InvalidSignatureForAttributeException::class);
});

it('resolvedAt with a default-scope signature returns the base column value (the findFirstMatch filter)', function (): void {
    DefaultScopeGuard::reset();

    $registry = loadShippedRegistry();
    [, , , , , , $resolver] = buildResolverStack($registry);

    $product = new DefaultScopeProduct();
    $product->name = 'base-name';

    // ScopeSignature with locale at its default value ('default').
    // walkAt → findFirstMatch filters 'default' from walkUp results → empty walk →
    // notFound → resolvedAt falls back to the base column property.
    $defaultSignature = new ScopeSignature(['locale' => 'default']);

    $result = $resolver->resolvedAt($product, 'name', $defaultSignature);

    expect($result)->toBe('base-name');
});
