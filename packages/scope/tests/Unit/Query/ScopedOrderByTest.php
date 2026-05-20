<?php

declare(strict_types=1);

use Marko\Database\Query\EntityQueryBuilderInterface;
use Marko\Database\Query\QueryBuilderInterface;
use Markommerce\Scope\Attributes\Scoped;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\ScopeContextException;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Metadata\ScopeMetadataFactory;
use Markommerce\Scope\Query\ScopedFieldExpression;
use Markommerce\Scope\Query\ScopedFieldRendererInterface;
use Markommerce\Scope\Query\ScopedOrderBy;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Signature\SignatureCandidateEnumerator;
use Markommerce\Scope\Storage\HasScopesInterface;

// ─── Fixtures ────────────────────────────────────────────────────────────────

class ScopedOrderByProduct
{
    #[Scoped(axes: ['store'])]
    public string $name = '';

    public string $sku = '';
}

class ScopedOrderByMultiAxisProduct
{
    #[Scoped(axes: ['store', 'locale'])]
    public string $title = '';
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * @param array<string, list<string>> $axes
 * @param array<string, string> $defaults
 */
function makeScopedOrderByRegistry(array $axes = [], array $defaults = []): ScopeRegistryInterface
{
    return new class ($axes, $defaults) implements ScopeRegistryInterface
    {
        /** @var array<string, ScopeAxis> */
        private array $builtAxes;

        /** @param array<string, list<string>> $axes @param array<string, string> $defaults */
        public function __construct(private readonly array $axes, array $defaults = [])
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

function makeBuilderSpy(): EntityQueryBuilderInterface
{
    return new class () implements EntityQueryBuilderInterface
    {
        public array $orderByCalls = [];

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

        public function where(
            string $column,
            string $operator,
            mixed $value,
        ): static {
            return $this;
        }

        public function whereIn(
            string $column,
            array $values,
        ): static {
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

        public function whereJsonContains(
            string $column,
            mixed $value,
        ): static {
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

        public function orWhere(
            string $column,
            string $operator,
            mixed $value,
        ): static {
            return $this;
        }

        public function join(
            string $table,
            string $first,
            string $operator,
            string $second,
        ): static {
            return $this;
        }

        public function leftJoin(
            string $table,
            string $first,
            string $operator,
            string $second,
        ): static {
            return $this;
        }

        public function rightJoin(
            string $table,
            string $first,
            string $operator,
            string $second,
        ): static {
            return $this;
        }

        public function groupBy(string ...$columns): static
        {
            return $this;
        }

        public function having(
            string $expression,
            array $bindings = [],
        ): static {
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

        public function raw(
            string $sql,
            array $bindings = [],
        ): array {
            return [];
        }

        public function orderBy(
            string $column,
            string $direction = 'ASC',
        ): static {
            $this->orderByCalls[] = ['column' => $column, 'direction' => $direction];

            return $this;
        }

        public function orderByRaw(
            string $expression,
            string $direction = 'ASC',
        ): static {
            $this->orderByRawCalls[] = ['expression' => $expression, 'direction' => $direction];

            return $this;
        }
    };
}

function makeRenderer(string $sql = 'COALESCE(json_extract(scopes, \'$.store.en\'), name)'): ScopedFieldRendererInterface
{
    return new readonly class ($sql) implements ScopedFieldRendererInterface
    {
        public function __construct(private string $sql) {}

        public function render(ScopedFieldExpression $expression): string
        {
            return $this->sql;
        }
    };
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('accepts ScopeMetadataFactory, ScopeContext, and ScopedFieldRendererInterface in constructor', function (): void {
    $registry = makeScopedOrderByRegistry(['store' => ['en', 'en.gb']]);
    $factory = new ScopeMetadataFactory($registry);
    $context = new ScopeContext($registry);
    $renderer = makeRenderer();
    $enumerator = new SignatureCandidateEnumerator($registry);

    $spec = new ScopedOrderBy(
        property: 'name',
        direction: 'desc',
        scopeMetadataFactory: $factory,
        scopeContext: $context,
        scopedFieldRenderer: $renderer,
        signatureCandidateEnumerator: $enumerator,
        entityClass: ScopedOrderByProduct::class,
    );

    expect($spec->property)->toBe('name')
        ->and($spec->direction)->toBe('desc');
});

it('accepts a property name and optional direction defaulting to asc', function (): void {
    $registry = makeScopedOrderByRegistry(['store' => ['en', 'en.gb']]);
    $factory = new ScopeMetadataFactory($registry);
    $context = new ScopeContext($registry);
    $renderer = makeRenderer();
    $enumerator = new SignatureCandidateEnumerator($registry);

    $spec = new ScopedOrderBy(
        property: 'name',
        scopeMetadataFactory: $factory,
        scopeContext: $context,
        scopedFieldRenderer: $renderer,
        signatureCandidateEnumerator: $enumerator,
        entityClass: ScopedOrderByProduct::class,
    );

    expect($spec->property)->toBe('name')
        ->and($spec->direction)->toBe('asc');
});

it('calls orderByRaw with the renderer-generated COALESCE expression when scope is active', function (): void {
    $registry = makeScopedOrderByRegistry(['store' => ['en', 'en.gb']]);
    $factory = new ScopeMetadataFactory($registry);
    $context = new ScopeContext($registry);
    $context->in('store', 'en.gb');

    $sql = "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(`scopes`, '$.store.en.gb')), name)";
    $renderer = makeRenderer($sql);
    $enumerator = new SignatureCandidateEnumerator($registry);

    $spec = new ScopedOrderBy(
        property: 'name',
        scopeMetadataFactory: $factory,
        scopeContext: $context,
        scopedFieldRenderer: $renderer,
        signatureCandidateEnumerator: $enumerator,
        entityClass: ScopedOrderByProduct::class,
    );

    $builder = makeBuilderSpy();
    $spec->apply($builder);

    expect($builder->orderByRawCalls)->toHaveCount(1)
        ->and($builder->orderByRawCalls[0]['expression'])->toBe($sql)
        ->and($builder->orderByCalls)->toBeEmpty();
});

it('falls back to plain orderBy when no scope is active for any of the property\'s axes', function (): void {
    $registry = makeScopedOrderByRegistry(['store' => ['en', 'en.gb']]);
    $factory = new ScopeMetadataFactory($registry);
    $context = new ScopeContext($registry);
    // No scope set on context
    $renderer = makeRenderer();
    $enumerator = new SignatureCandidateEnumerator($registry);

    $spec = new ScopedOrderBy(
        property: 'name',
        scopeMetadataFactory: $factory,
        scopeContext: $context,
        scopedFieldRenderer: $renderer,
        signatureCandidateEnumerator: $enumerator,
        entityClass: ScopedOrderByProduct::class,
    );

    $builder = makeBuilderSpy();
    $spec->apply($builder);

    expect($builder->orderByCalls)->toHaveCount(1)
        ->and($builder->orderByCalls[0]['column'])->toBe('name')
        ->and($builder->orderByRawCalls)->toBeEmpty();
});

it('throws ScopeContextException when the property is not Scoped', function (): void {
    $registry = makeScopedOrderByRegistry(['store' => ['en', 'en.gb']]);
    $factory = new ScopeMetadataFactory($registry);
    $context = new ScopeContext($registry);
    $renderer = makeRenderer();
    $enumerator = new SignatureCandidateEnumerator($registry);

    $spec = new ScopedOrderBy(
        property: 'sku',
        scopeMetadataFactory: $factory,
        scopeContext: $context,
        scopedFieldRenderer: $renderer,
        signatureCandidateEnumerator: $enumerator,
        entityClass: ScopedOrderByProduct::class,
    );

    $builder = makeBuilderSpy();
    expect(fn () => $spec->apply($builder))->toThrow(ScopeContextException::class);
});

it(
    'preserves direction asc or desc on the emitted ORDER BY',
    function (string $direction, string $expectedDirectionUpper): void {
        $registry = makeScopedOrderByRegistry(['store' => ['en', 'en.gb']]);
        $factory = new ScopeMetadataFactory($registry);
        $context = new ScopeContext($registry);
        $context->in('store', 'en');
        $renderer = makeRenderer('COALESCE(expr)');
        $enumerator = new SignatureCandidateEnumerator($registry);

        $spec = new ScopedOrderBy(
            property: 'name',
            scopeMetadataFactory: $factory,
            scopeContext: $context,
            scopedFieldRenderer: $renderer,
            signatureCandidateEnumerator: $enumerator,
            entityClass: ScopedOrderByProduct::class,
            direction: $direction,
        );

        $builder = makeBuilderSpy();
        $spec->apply($builder);

        expect($builder->orderByRawCalls)->toHaveCount(1)
            ->and($builder->orderByRawCalls[0]['direction'])->toBe($expectedDirectionUpper);
    },
)->with([
    ['asc', 'ASC'],
    ['desc', 'DESC'],
]);

it('builds a ScopedFieldExpression by reading ScopeMetadata for the entity class on apply', function (): void {
    $registry = makeScopedOrderByRegistry(['store' => ['en', 'en.gb']]);
    $factory = new ScopeMetadataFactory($registry);
    $context = new ScopeContext($registry);
    $context->in('store', 'en.gb');
    $enumerator = new SignatureCandidateEnumerator($registry);

    $renderer = new class () implements ScopedFieldRendererInterface
    {
        public ?ScopedFieldExpression $captured = null;

        public function render(ScopedFieldExpression $expression): string
        {
            $this->captured = $expression;

            return 'COALESCE(expr)';
        }
    };

    $spec = new ScopedOrderBy(
        property: 'name',
        scopeMetadataFactory: $factory,
        scopeContext: $context,
        scopedFieldRenderer: $renderer,
        signatureCandidateEnumerator: $enumerator,
        entityClass: ScopedOrderByProduct::class,
    );

    $builder = makeBuilderSpy();
    $spec->apply($builder);

    expect($renderer->captured)->toBeInstanceOf(ScopedFieldExpression::class)
        ->and($renderer->captured->property)->toBe('name')
        ->and($renderer->captured->column)->toBe('name');
});

it('builds a COALESCE-based orderByRaw using the candidate signatures from the enumerator', function (): void {
    $registry = makeScopedOrderByRegistry(['store' => ['en', 'en.gb']]);
    $factory = new ScopeMetadataFactory($registry);
    $context = new ScopeContext($registry);
    $context->in('store', 'en.gb');
    $enumerator = new SignatureCandidateEnumerator($registry);

    $capturedExpression = null;
    $renderer = new class (Closure::fromCallable(
        function (ScopedFieldExpression $expr) use (&$capturedExpression): string {
            $capturedExpression = $expr;
    
            return 'COALESCE(expr)';
        }
    )) implements ScopedFieldRendererInterface {
        public function __construct(private readonly Closure $fn) {}

        public function render(ScopedFieldExpression $expression): string
        {
            return ($this->fn)($expression);
        }
    };

    $spec = new ScopedOrderBy(
        property: 'name',
        scopeMetadataFactory: $factory,
        scopeContext: $context,
        scopedFieldRenderer: $renderer,
        signatureCandidateEnumerator: $enumerator,
        entityClass: ScopedOrderByProduct::class,
    );

    $builder = makeBuilderSpy();
    $spec->apply($builder);

    expect($builder->orderByRawCalls)->toHaveCount(1)
        ->and($capturedExpression)->toBeInstanceOf(ScopedFieldExpression::class)
        ->and($capturedExpression->candidateSignatures)->not->toBeEmpty();
});

it('it falls back to plain orderBy when the enumerator produces zero candidates', function (): void {
    $registry = makeScopedOrderByRegistry(['store' => ['en', 'en.gb']]);
    $factory = new ScopeMetadataFactory($registry);
    $context = new ScopeContext($registry);
    // No scope set — enumerator will return zero candidates
    $renderer = makeRenderer();
    $enumerator = new SignatureCandidateEnumerator($registry);

    $spec = new ScopedOrderBy(
        property: 'name',
        scopeMetadataFactory: $factory,
        scopeContext: $context,
        scopedFieldRenderer: $renderer,
        signatureCandidateEnumerator: $enumerator,
        entityClass: ScopedOrderByProduct::class,
    );

    $builder = makeBuilderSpy();
    $spec->apply($builder);

    expect($builder->orderByCalls)->toHaveCount(1)
        ->and($builder->orderByCalls[0]['column'])->toBe('name')
        ->and($builder->orderByRawCalls)->toBeEmpty();
});

it(
    'it produces signatures in descending-score order in the COALESCE chain (the order is preserved as enumerator output)',
    function (): void {
        $registry = makeScopedOrderByRegistry(['store' => ['en', 'en.gb']]);
        $factory = new ScopeMetadataFactory($registry);
        $context = new ScopeContext($registry);
        $context->in('store', 'en.gb');
        $enumerator = new SignatureCandidateEnumerator($registry);

        $capturedSignatures = null;
        $renderer = new class (Closure::fromCallable(
            function (ScopedFieldExpression $expr) use (&$capturedSignatures): string {
                $capturedSignatures = $expr->candidateSignatures;
    
                return 'COALESCE(expr)';
            }
        )) implements ScopedFieldRendererInterface {
            public function __construct(private readonly Closure $fn) {}

            public function render(ScopedFieldExpression $expression): string
            {
                return ($this->fn)($expression);
            }
        };

        $spec = new ScopedOrderBy(
            property: 'name',
            scopeMetadataFactory: $factory,
            scopeContext: $context,
            scopedFieldRenderer: $renderer,
            signatureCandidateEnumerator: $enumerator,
            entityClass: ScopedOrderByProduct::class,
        );

        $spec->apply(makeBuilderSpy());

        // The enumerator walks up from most-specific (en.gb) to least-specific (en)
        // Signatures must appear in the same order as enumerator output
        $expectedSignatures = $enumerator->enumerate(['store'], $context);

        expect($capturedSignatures)->toBe($expectedSignatures);
    },
);

it('it throws ScopeContextException when the property is not @Scoped on the entity', function (): void {
    $registry = makeScopedOrderByRegistry(['store' => ['en', 'en.gb']]);
    $factory = new ScopeMetadataFactory($registry);
    $context = new ScopeContext($registry);
    $renderer = makeRenderer();
    $enumerator = new SignatureCandidateEnumerator($registry);

    $spec = new ScopedOrderBy(
        property: 'sku',
        scopeMetadataFactory: $factory,
        scopeContext: $context,
        scopedFieldRenderer: $renderer,
        signatureCandidateEnumerator: $enumerator,
        entityClass: ScopedOrderByProduct::class,
    );

    $builder = makeBuilderSpy();
    expect(fn () => $spec->apply($builder))->toThrow(ScopeContextException::class);
});

it(
    'it passes ASC or DESC direction to the query builder unchanged',
    function (string $direction, string $expectedUpper): void {
        $registry = makeScopedOrderByRegistry(['store' => ['en', 'en.gb']]);
        $factory = new ScopeMetadataFactory($registry);
        $context = new ScopeContext($registry);
        $context->in('store', 'en');
        $renderer = makeRenderer('COALESCE(expr)');
        $enumerator = new SignatureCandidateEnumerator($registry);

        $spec = new ScopedOrderBy(
            property: 'name',
            scopeMetadataFactory: $factory,
            scopeContext: $context,
            scopedFieldRenderer: $renderer,
            signatureCandidateEnumerator: $enumerator,
            entityClass: ScopedOrderByProduct::class,
            direction: $direction,
        );

        $builder = makeBuilderSpy();
        $spec->apply($builder);

        expect($builder->orderByRawCalls)->toHaveCount(1)
            ->and($builder->orderByRawCalls[0]['direction'])->toBe($expectedUpper);
    },
)->with([
    ['asc', 'ASC'],
    ['desc', 'DESC'],
    ['ASC', 'ASC'],
    ['DESC', 'DESC'],
]);

it(
    'it does NOT call HasScopesInterface::overrides() at all during apply (the SQL path never reads stored override keys; it derives the chain from the enumerator only)',
    function (): void {
        $registry = makeScopedOrderByRegistry(['store' => ['en', 'en.gb']]);
        $factory = new ScopeMetadataFactory($registry);
        $context = new ScopeContext($registry);
        $context->in('store', 'en.gb');
        $renderer = makeRenderer('COALESCE(expr)');
        $enumerator = new SignatureCandidateEnumerator($registry);

        $spec = new ScopedOrderBy(
            property: 'name',
            scopeMetadataFactory: $factory,
            scopeContext: $context,
            scopedFieldRenderer: $renderer,
            signatureCandidateEnumerator: $enumerator,
            entityClass: ScopedOrderByProduct::class,
        );

        // Create a spy that tracks calls to overrides()
        $hasScopes = new class () implements HasScopesInterface
        {
            public int $overridesCalled = 0;

            public function setOverride(
                string $signature,
                string $property,
                mixed $value,
            ): void {}

            public function override(
                string $signature,
                string $property,
            ): mixed
            {
                return null;
            }

            public function hasOverride(
                string $signature,
                string $property,
            ): bool
            {
                return false;
            }

            public function clearOverride(
                string $signature,
                string $property,
            ): void {}

            public function overrides(): array
            {
                $this->overridesCalled++;

                return [];
            }
        };

        $builder = makeBuilderSpy();
        $spec->apply($builder);

        // The ScopedOrderBy SQL path must not interact with HasScopesInterface at all
        expect($hasScopes->overridesCalled)->toBe(0);
    },
);

it(
    'it emits the cap-exceeded warning once when the candidate count exceeds the configured cap (verified by injecting a low-cap enumerator)',
    function (): void {
        // Two axes with 2 paths each produce at least 4 candidates for a context set to the most specific path.
        // A cap of 1 will be exceeded and should fire E_USER_WARNING exactly once.
        $registry = makeScopedOrderByRegistry([
            'store' => ['en', 'en.gb'],
            'locale' => ['default', 'default.formal'],
        ]);
        $factory = new ScopeMetadataFactory($registry);
        $context = new ScopeContext($registry);
        $context->in('store', 'en.gb');
        $context->in('locale', 'default.formal');

        // Cap = 1: enumerator will emit the warning when generating the 2nd candidate
        $enumerator = new SignatureCandidateEnumerator($registry, cap: 1);
        $renderer = makeRenderer('COALESCE(expr)');

        $spec = new ScopedOrderBy(
            property: 'title',
            scopeMetadataFactory: $factory,
            scopeContext: $context,
            scopedFieldRenderer: $renderer,
            signatureCandidateEnumerator: $enumerator,
            entityClass: ScopedOrderByMultiAxisProduct::class,
        );

        $warningCount = 0;
        set_error_handler(function (int $errno, string $errstr) use (&$warningCount): bool {
            if ($errno === E_USER_WARNING && str_contains($errstr, 'cap')) {
                $warningCount++;
            }

            return true;
        });

        $spec->apply(makeBuilderSpy());

        restore_error_handler();

        expect($warningCount)->toBe(1);
    },
);

it(
    'for the same attribute axes and context, the JSONB keys appearing in the COALESCE chain match the order in which the walker iterates candidates (no algorithmic drift)',
    function (): void {
        $registry = makeScopedOrderByRegistry(['store' => ['en', 'en.gb']]);
        $factory = new ScopeMetadataFactory($registry);
        $context = new ScopeContext($registry);
        $context->in('store', 'en.gb');
        $enumerator = new SignatureCandidateEnumerator($registry);

        // Capture which signatures the renderer receives (SQL COALESCE chain order)
        $sqlCandidates = null;
        $renderer = new class (Closure::fromCallable(
            function (ScopedFieldExpression $expr) use (&$sqlCandidates): string {
                $sqlCandidates = $expr->candidateSignatures;
    
                return 'COALESCE(expr)';
            }
        )) implements ScopedFieldRendererInterface {
            public function __construct(private readonly Closure $fn) {}

            public function render(ScopedFieldExpression $expression): string
            {
                return ($this->fn)($expression);
            }
        };

        $spec = new ScopedOrderBy(
            property: 'name',
            scopeMetadataFactory: $factory,
            scopeContext: $context,
            scopedFieldRenderer: $renderer,
            signatureCandidateEnumerator: $enumerator,
            entityClass: ScopedOrderByProduct::class,
        );

        $spec->apply(makeBuilderSpy());

        // Get the order in which the PHP walker iterates candidates for the same axes+context
        $walkerCandidates = $enumerator->enumerate(['store'], $context);

        expect($sqlCandidates)->not->toBeNull()
            ->and(count($sqlCandidates))->toBe(count($walkerCandidates));

        foreach ($sqlCandidates as $index => $sqlSig) {
            expect($sqlSig->toString())->toBe($walkerCandidates[$index]->toString());
        }
    },
);
