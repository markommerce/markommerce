<?php

declare(strict_types=1);

use Markommerce\Indexer\ScopePassRunner;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Signature\ScopeSignature;

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Build a minimal ScopeContext that knows about the provided axes.
 *
 * @param array<string, list<string>> $axesMap axis name => list of all valid paths
 */
function makeScopeContext(array $axesMap = []): ScopeContext
{
    $registry = new class ($axesMap) implements ScopeRegistryInterface
    {
        /** @var array<string, ScopeAxis> */
        private array $builtAxes;

        /** @param array<string, list<string>> $axesMap */
        public function __construct(array $axesMap)
        {
            $this->builtAxes = [];
            foreach ($axesMap as $name => $paths) {
                $hierarchy = new ScopeHierarchy($paths);
                $this->builtAxes[$name] = new ScopeAxis(
                    name: $name,
                    hierarchy: $hierarchy,
                    default: $paths[0] ?? '',
                );
            }
        }

        public function hasAxis(string $name): bool
        {
            return isset($this->builtAxes[$name]);
        }

        public function getAxis(string $name): ScopeAxis
        {
            return $this->builtAxes[$name] ?? throw UnknownAxisException::forAxis($name);
        }

        /** @return list<string> */
        public function listAxes(): array
        {
            return array_keys($this->builtAxes);
        }

        public function getHierarchy(string $axisName): ScopeHierarchy
        {
            return $this->getAxis($axisName)->hierarchy;
        }
    };

    return new ScopeContext($registry);
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('runs the callback once with null for the base pass plus once per signature', function (): void {
    $scopeContext = makeScopeContext(['locale' => ['default', 'en', 'fr']]);
    $runner = new ScopePassRunner($scopeContext);

    $signatures = [
        ScopeSignature::fromArray(['locale' => 'en']),
        ScopeSignature::fromArray(['locale' => 'fr']),
    ];

    $received = [];
    $runner->each($signatures, function (?ScopeSignature $sig) use (&$received): void {
        $received[] = $sig;
    });

    expect($received)->toHaveCount(3);
    expect($received[0])->toBeNull();
    expect($received[1])->toBeInstanceOf(ScopeSignature::class);
    expect($received[1]->toString())->toBe('locale:en');
    expect($received[2])->toBeInstanceOf(ScopeSignature::class);
    expect($received[2]->toString())->toBe('locale:fr');
});

it('clears all axes before the base pass and sets every signature axis before each scoped pass', function (): void {
    $scopeContext = makeScopeContext([
        'locale' => ['default', 'en', 'fr'],
        'market' => ['default', 'us', 'eu'],
    ]);
    // Pre-set an ambient scope that should be cleared during passes
    $scopeContext->in('locale', 'fr');
    $scopeContext->in('market', 'eu');

    $runner = new ScopePassRunner($scopeContext);

    $signatures = [
        ScopeSignature::fromArray(['locale' => 'en', 'market' => 'us']),
    ];

    $capturedDuringBase = null;
    $capturedDuringScoped = null;

    $runner->each(
        $signatures,
        function (?ScopeSignature $sig) use ($scopeContext, &$capturedDuringBase, &$capturedDuringScoped): void {
            if ($sig === null) {
                // During base pass: all axes should be cleared
                $capturedDuringBase = $scopeContext->state();
            } else {
                // During scoped pass: axes from signature should be set
                $capturedDuringScoped = $scopeContext->state();
            }
        },
    );

    // Base pass: context should have been clear
    expect($capturedDuringBase)->toBe([]);

    // Scoped pass: context should have exactly the signature axes
    expect($capturedDuringScoped)->toBe(['locale' => 'en', 'market' => 'us']);
});

it('restores the full multi-axis ambient scope context after the passes complete', function (): void {
    $scopeContext = makeScopeContext([
        'locale' => ['default', 'en', 'fr'],
        'market' => ['default', 'us', 'eu'],
    ]);
    // Pre-set a multi-axis ambient scope
    $scopeContext->in('locale', 'fr');
    $scopeContext->in('market', 'eu');

    $runner = new ScopePassRunner($scopeContext);

    $signatures = [
        ScopeSignature::fromArray(['locale' => 'en', 'market' => 'us']),
    ];

    $runner->each($signatures, function (?ScopeSignature $sig): void {
        // noop
    });

    // After all passes: both axes should be restored to pre-call state
    expect($scopeContext->get('locale'))->toBe('fr');
    expect($scopeContext->get('market'))->toBe('eu');
});

it('restores the ambient scope context even when a pass throws', function (): void {
    $scopeContext = makeScopeContext([
        'locale' => ['default', 'en', 'fr'],
        'market' => ['default', 'us', 'eu'],
    ]);
    $scopeContext->in('locale', 'fr');
    $scopeContext->in('market', 'eu');

    $runner = new ScopePassRunner($scopeContext);

    $signatures = [
        ScopeSignature::fromArray(['locale' => 'en', 'market' => 'us']),
    ];

    $thrownException = null;

    try {
        $runner->each($signatures, function (?ScopeSignature $sig): void {
            if ($sig !== null) {
                throw new RuntimeException('index failure');
            }
        });
    } catch (RuntimeException $e) {
        $thrownException = $e;
    }

    // Exception should have propagated
    expect($thrownException)->toBeInstanceOf(RuntimeException::class);
    expect($thrownException->getMessage())->toBe('index failure');

    // And both axes should be restored
    expect($scopeContext->get('locale'))->toBe('fr');
    expect($scopeContext->get('market'))->toBe('eu');
});
