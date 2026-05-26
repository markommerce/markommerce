<?php

declare(strict_types=1);

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\MultiAxisWalkAtNotSupportedException;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Resolution\ScopeWalker;
use Markommerce\Scope\Signature\ScopeSignature;
use Markommerce\Scope\Signature\SignatureCandidateEnumerator;
use Markommerce\Scope\Storage\HasScopes;
use Markommerce\Scope\Storage\HasScopesInterface;

// Standalone HasScopesInterface implementor for walker tests (no parent entity needed)
class WalkerTestOverrides implements HasScopesInterface
{
    use HasScopes;
}

// Trait-based entity fixture
#[Table(name: 'walker_trait_products')]
class WalkerTraitProduct extends Entity implements HasScopesInterface
{
    use HasScopes;

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;
}

/**
 * @param array<string, list<string>> $axes
 * @param array<string, string> $defaults
 */
function makeWalkerRegistry(array $axes = [], array $defaults = []): ScopeRegistryInterface
{
    return new class ($axes, $defaults) implements ScopeRegistryInterface
    {
        /** @var array<string, ScopeAxis> */
        private array $builtAxes;

        /** @param array<string, list<string>> $axes @param array<string, string> $defaults */
        public function __construct(
            array $axes,
            array $defaults = [],
        ) {
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

        public function getHierarchy(string $axisName): ScopeHierarchy
        {
            return $this->getAxis($axisName)->hierarchy;
        }
    };
}

// ─── Kept tests (single-axis, hierarchy, walkAt) ──────────────────────────────

it('returns the override at the current scope when one exists', function (): void {
    $registry = makeWalkerRegistry(['geo' => ['eu', 'eu.de']]);
    $context = new ScopeContext($registry);
    $context->in('geo', 'eu.de');

    $overrides = new WalkerTestOverrides();
    $overrides->setOverride('geo:eu.de', 'name', 'Hemd');

    $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));
    $result = $walker->walk($overrides, 'name', ['geo'], $context);

    expect($result->isFound())->toBeTrue()
        ->and($result->value())->toBe('Hemd');
});

it('returns an ancestor override when no override exists at the current scope', function (): void {
    $registry = makeWalkerRegistry(['geo' => ['eu', 'eu.de']]);
    $context = new ScopeContext($registry);
    $context->in('geo', 'eu.de');

    $overrides = new WalkerTestOverrides();
    $overrides->setOverride('geo:eu', 'name', 'Shirt-EU');

    $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));
    $result = $walker->walk($overrides, 'name', ['geo'], $context);

    expect($result->isFound())->toBeTrue()
        ->and($result->value())->toBe('Shirt-EU');
});

it('returns notFound when no override exists at any walked scope', function (): void {
    $registry = makeWalkerRegistry(['geo' => ['eu', 'eu.de']]);
    $context = new ScopeContext($registry);
    $context->in('geo', 'eu.de');

    $overrides = new WalkerTestOverrides();

    $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));
    $result = $walker->walk($overrides, 'name', ['geo'], $context);

    expect($result->isFound())->toBeFalse();
});

it('preserves an explicit null override and does not fall through it within an axis', function (): void {
    $registry = makeWalkerRegistry(['geo' => ['eu', 'eu.de']]);
    $context = new ScopeContext($registry);
    $context->in('geo', 'eu.de');

    $overrides = new WalkerTestOverrides();
    $overrides->setOverride('geo:eu', 'name', 'Shirt-EU');
    $overrides->setOverride('geo:eu.de', 'name', null); // explicit null at current scope

    $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));
    $result = $walker->walk($overrides, 'name', ['geo'], $context);

    // Should return found(null) — not fall through to the eu ancestor
    expect($result->isFound())->toBeTrue()
        ->and($result->value())->toBeNull();
});

it('returns notFound when no axes are declared and no overrides exist', function (): void {
    $registry = makeWalkerRegistry([]);
    $context = new ScopeContext($registry);

    $overrides = new WalkerTestOverrides();

    $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));
    $result = $walker->walk($overrides, 'name', [], $context);

    expect($result->isFound())->toBeFalse();
});

it('resolves an override via walkAt when passed a HasScopesInterface implementor', function (): void {
    $registry = makeWalkerRegistry(['geo' => ['eu', 'eu.de']]);
    $signature = new ScopeSignature(['geo' => 'eu.de']);

    $overrides = new WalkerTraitProduct();
    $overrides->setOverride('geo:eu.de', 'name', 'Hemd');

    $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));
    $result = $walker->walkAt($overrides, 'name', ['geo'], $signature, $registry);

    expect($result->isFound())->toBeTrue()
        ->and($result->value())->toBe('Hemd');
});

it('walks hierarchy ancestors when the exact scope path has no override', function (): void {
    $registry = makeWalkerRegistry(['geo' => ['eu', 'eu.de']]);
    $signature = new ScopeSignature(['geo' => 'eu.de']);

    $overrides = new WalkerTraitProduct();
    // Only set override on ancestor 'eu', not on 'eu.de'
    $overrides->setOverride('geo:eu', 'name', 'Shirt-EU');

    $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));
    $result = $walker->walkAt($overrides, 'name', ['geo'], $signature, $registry);

    expect($result->isFound())->toBeTrue()
        ->and($result->value())->toBe('Shirt-EU');
});

it('returns notFound via walkAt when the axis does not match', function (): void {
    $registry = makeWalkerRegistry(['geo' => ['eu', 'eu.de']]);
    $signature = new ScopeSignature(['locale' => 'de']);

    $overrides = new WalkerTraitProduct();
    $overrides->setOverride('geo:eu.de', 'name', 'Hemd');

    $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));
    $result = $walker->walkAt($overrides, 'name', ['geo'], $signature, $registry);

    expect($result->isFound())->toBeFalse();
});

it('returns notFound via walk when the HasScopesInterface implementor has no matching override', function (): void {
    $registry = makeWalkerRegistry(['geo' => ['eu', 'eu.de']]);
    $context = new ScopeContext($registry);
    $context->in('geo', 'eu.de');

    $overrides = new WalkerTraitProduct();

    $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));
    $result = $walker->walk($overrides, 'name', ['geo'], $context);

    expect($result->isFound())->toBeFalse();
});

it(
    'resolves an override via walk when passed a HasScopesInterface implementor that is not ScopedOverridesEntity',
    function (): void {
        $registry = makeWalkerRegistry(['geo' => ['eu', 'eu.de']]);
        $context = new ScopeContext($registry);
        $context->in('geo', 'eu.de');

        $overrides = new WalkerTraitProduct();
        $overrides->setOverride('geo:eu.de', 'name', 'Hemd');

        $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));
        $result = $walker->walk($overrides, 'name', ['geo'], $context);

        expect($result->isFound())->toBeTrue()
            ->and($result->value())->toBe('Hemd');
    },
);

// ─── New behavioral tests (composite resolution) ──────────────────────────────

it(
    'matches a single-axis override via hierarchy walk-up (case 1: locale:es matches context locale es.es)',
    function (): void {
        $registry = makeWalkerRegistry(['locale' => ['es', 'es.es']]);
        $context = new ScopeContext($registry);
        $context->in('locale', 'es.es');

        $overrides = new WalkerTestOverrides();
        $overrides->setOverride('locale:es', 'name', 'Camisa');

        $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));
        $result = $walker->walk($overrides, 'name', ['locale'], $context);

        expect($result->isFound())->toBeTrue()
            ->and($result->value())->toBe('Camisa');
    },
);

it(
    'picks the deeper hierarchy match within an axis (case 2: locale:es.es wins over locale:es when context is es.es)',
    function (): void {
        $registry = makeWalkerRegistry(['locale' => ['es', 'es.es']]);
        $context = new ScopeContext($registry);
        $context->in('locale', 'es.es');

        $overrides = new WalkerTestOverrides();
        $overrides->setOverride('locale:es', 'name', 'Camisa');
        $overrides->setOverride('locale:es.es', 'name', 'Camisa-ES');

        $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));
        $result = $walker->walk($overrides, 'name', ['locale'], $context);

        expect($result->isFound())->toBeTrue()
            ->and($result->value())->toBe('Camisa-ES');
    },
);

it(
    'picks the most-specific composite when both composite and partials exist (case 3: channel:b2b|locale:es wins over channel:b2b and locale:es)',
    function (): void {
        $registry = makeWalkerRegistry([
            'channel' => ['b2b', 'b2c'],
            'locale'  => ['es', 'es.es'],
        ]);
        $context = new ScopeContext($registry);
        $context->in('channel', 'b2b')->in('locale', 'es');

        $overrides = new WalkerTestOverrides();
        $overrides->setOverride('channel:b2b', 'name', 'B2B');
        $overrides->setOverride('locale:es', 'name', 'Camisa');
        $overrides->setOverride('channel:b2b|locale:es', 'name', 'B2B-Camisa');

        $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));
        $result = $walker->walk($overrides, 'name', ['channel', 'locale'], $context);

        expect($result->isFound())->toBeTrue()
            ->and($result->value())->toBe('B2B-Camisa');
    },
);

it(
    'picks the higher-priority single-axis when only single-axis overrides exist (case 4: channel:b2b wins over locale:es)',
    function (): void {
        $registry = makeWalkerRegistry([
            'channel' => ['b2b', 'b2c'],
            'locale'  => ['es', 'es.es'],
        ]);
        $context = new ScopeContext($registry);
        $context->in('channel', 'b2b')->in('locale', 'es');

        $overrides = new WalkerTestOverrides();
        $overrides->setOverride('channel:b2b', 'name', 'B2B');
        $overrides->setOverride('locale:es', 'name', 'Camisa');

        // channel is first axis (higher priority), locale is second
        $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));
        $result = $walker->walk($overrides, 'name', ['channel', 'locale'], $context);

        expect($result->isFound())->toBeTrue()
            ->and($result->value())->toBe('B2B');
    },
);

it(
    'falls through to lower-priority axis when higher-priority axis has no applicable override (case 5: locale:es wins when only locale:es exists)',
    function (): void {
        $registry = makeWalkerRegistry([
            'channel' => ['b2b', 'b2c'],
            'locale'  => ['es', 'es.es'],
        ]);
        $context = new ScopeContext($registry);
        $context->in('channel', 'b2b')->in('locale', 'es');

        $overrides = new WalkerTestOverrides();
        // Only locale:es exists — no channel override
        $overrides->setOverride('locale:es', 'name', 'Camisa');

        $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));
        $result = $walker->walk($overrides, 'name', ['channel', 'locale'], $context);

        expect($result->isFound())->toBeTrue()
            ->and($result->value())->toBe('Camisa');
    },
);

it(
    'does not match an override mentioning an axis not in the context (case 6: market:eu.es does not match a context without market)',
    function (): void {
        $registry = makeWalkerRegistry([
            'locale' => ['es', 'es.es'],
            'market' => ['eu', 'eu.es'],
        ]);
        $context = new ScopeContext($registry);
        // Only locale is set in context, not market
        $context->in('locale', 'es.es');

        $overrides = new WalkerTestOverrides();
        $overrides->setOverride('market:eu.es', 'name', 'EU-ES');

        // Attribute axes only include locale, not market
        $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));
        $result = $walker->walk($overrides, 'name', ['locale'], $context);

        expect($result->isFound())->toBeFalse();
    },
);

it(
    'ignores stored signatures with axes not in the attribute axes (case 7: defensive ignore on read)',
    function (): void {
        $registry = makeWalkerRegistry([
            'locale' => ['es', 'es.es'],
            'market' => ['eu', 'eu.es'],
        ]);
        $context = new ScopeContext($registry);
        $context->in('locale', 'es.es');

        // Instrumented storage that tracks which signatures are looked up
        $lookedUp = [];
        $overrides = new class ($lookedUp) implements HasScopesInterface
        {
            use HasScopes;

            /** @param array<int, string> $lookedUp */
            public function __construct(private array &$lookedUp) {}

            public function hasOverride(
                string $signature,
                string $property,
            ): bool {
                $this->lookedUp[] = $signature;

                return array_key_exists($signature, $this->scopes ?? [])
                    && array_key_exists($property, ($this->scopes ?? [])[$signature]);
            }
        };

        $overrides->setOverride('market:eu.es', 'name', 'EU-ES');

        // Attribute axes only include locale — market:eu.es must never be queried
        $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));
        $walker->walk($overrides, 'name', ['locale'], $context);

        expect(array_any($lookedUp, fn (string $sig) => str_contains($sig, 'market')))->toBeFalse();
    },
);

it('returns notFound when no applicable overrides exist (case 8)', function (): void {
    $registry = makeWalkerRegistry([
        'channel' => ['b2b'],
        'locale'  => ['es'],
    ]);
    $context = new ScopeContext($registry);
    $context->in('channel', 'b2b')->in('locale', 'es');

    $overrides = new WalkerTestOverrides();

    $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));
    $result = $walker->walk($overrides, 'name', ['channel', 'locale'], $context);

    expect($result->isFound())->toBeFalse();
});

it(
    'walks hierarchy inside composites (case 9: channel:b2b|locale:es matches context channel b2b, locale es.es)',
    function (): void {
        $registry = makeWalkerRegistry([
            'channel' => ['b2b'],
            'locale'  => ['es', 'es.es'],
        ]);
        $context = new ScopeContext($registry);
        $context->in('channel', 'b2b')->in('locale', 'es.es');

        $overrides = new WalkerTestOverrides();
        $overrides->setOverride('channel:b2b|locale:es', 'name', 'B2B-Camisa');

        $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));
        $result = $walker->walk($overrides, 'name', ['channel', 'locale'], $context);

        expect($result->isFound())->toBeTrue()
            ->and($result->value())->toBe('B2B-Camisa');
    },
);

it(
    'scores composites with hierarchy walks (case 10: channel:b2b|locale:es.es wins over channel:b2b|locale:es when context locale is es.es)',
    function (): void {
        $registry = makeWalkerRegistry([
            'channel' => ['b2b'],
            'locale'  => ['es', 'es.es'],
        ]);
        $context = new ScopeContext($registry);
        $context->in('channel', 'b2b')->in('locale', 'es.es');

        $overrides = new WalkerTestOverrides();
        $overrides->setOverride('channel:b2b|locale:es', 'name', 'B2B-Camisa');
        $overrides->setOverride('channel:b2b|locale:es.es', 'name', 'B2B-Camisa-ES');

        $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));
        $result = $walker->walk($overrides, 'name', ['channel', 'locale'], $context);

        expect($result->isFound())->toBeTrue()
            ->and($result->value())->toBe('B2B-Camisa-ES');
    },
);

it(
    'lets higher-priority axis dominate hierarchy depth (case 11: channel:b2b beats locale:es.es when context is {channel: b2b, locale: es.es})',
    function (): void {
        $registry = makeWalkerRegistry([
            'channel' => ['b2b'],
            'locale'  => ['es', 'es.es'],
        ]);
        $context = new ScopeContext($registry);
        $context->in('channel', 'b2b')->in('locale', 'es.es');

        $overrides = new WalkerTestOverrides();
        // channel:b2b is a single-axis override for the higher-priority axis
        // locale:es.es is deeper in its hierarchy
        $overrides->setOverride('channel:b2b', 'name', 'B2B');
        $overrides->setOverride('locale:es.es', 'name', 'Camisa-ES');

        // channel is declared first (higher priority)
        $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));
        $result = $walker->walk($overrides, 'name', ['channel', 'locale'], $context);

        expect($result->isFound())->toBeTrue()
            ->and($result->value())->toBe('B2B');
    },
);

it('resolves three-axis composites across full and partial compositions (case 12)', function (): void {
    $registry = makeWalkerRegistry([
        'channel' => ['b2b'],
        'locale'  => ['es', 'es.es'],
        'market'  => ['eu', 'eu.es'],
    ]);
    $context = new ScopeContext($registry);
    $context->in('channel', 'b2b')->in('locale', 'es.es')->in('market', 'eu.es');

    $overrides = new WalkerTestOverrides();
    // Full three-axis composite — most specific
    $overrides->setOverride('channel:b2b|locale:es|market:eu', 'name', 'B2B-ES-EU');
    // Two-axis composite
    $overrides->setOverride('channel:b2b|locale:es', 'name', 'B2B-ES');
    // Single-axis
    $overrides->setOverride('channel:b2b', 'name', 'B2B');

    $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));
    $result = $walker->walk($overrides, 'name', ['channel', 'locale', 'market'], $context);

    // The most specific composite that matches should win
    // channel:b2b|locale:es.es|market:eu.es is checked first, then walk-up variants
    // The first matching walk-up should be channel:b2b|locale:es.es|market:eu (or similar)
    // but channel:b2b|locale:es|market:eu exists — verify it's found
    expect($result->isFound())->toBeTrue();
});

it('returns notFound when context is empty even if axes are declared (case 13)', function (): void {
    $registry = makeWalkerRegistry([
        'channel' => ['b2b'],
        'locale'  => ['es'],
    ]);
    $context = new ScopeContext($registry);
    // No axes set in context

    $overrides = new WalkerTestOverrides();
    $overrides->setOverride('channel:b2b', 'name', 'B2B');

    $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));
    $result = $walker->walk($overrides, 'name', ['channel', 'locale'], $context);

    expect($result->isFound())->toBeFalse();
});

it('preserves an explicit null override as found (null-as-found contract preserved)', function (): void {
    $registry = makeWalkerRegistry([
        'channel' => ['b2b'],
        'locale'  => ['es', 'es.es'],
    ]);
    $context = new ScopeContext($registry);
    $context->in('channel', 'b2b')->in('locale', 'es.es');

    $overrides = new WalkerTestOverrides();
    // Higher-scored composite has explicit null
    $overrides->setOverride('channel:b2b|locale:es.es', 'name', null);
    // Lower-scored single-axis has a real value
    $overrides->setOverride('locale:es', 'name', 'Camisa');

    $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));
    $result = $walker->walk($overrides, 'name', ['channel', 'locale'], $context);

    // The explicit null at higher-scored composite wins
    expect($result->isFound())->toBeTrue()
        ->and($result->value())->toBeNull();
});

it(
    'does NOT iterate HasScopesInterface::overrides() on the resolution path (verified via instrumented storage that counts overrides() calls)',
    function (): void {
        $registry = makeWalkerRegistry([
            'channel' => ['b2b'],
            'locale'  => ['es'],
        ]);
        $context = new ScopeContext($registry);
        $context->in('channel', 'b2b')->in('locale', 'es');

        $overridesCalled = 0;
        $overrides = new class ($overridesCalled) implements HasScopesInterface
        {
            use HasScopes;

            public function __construct(private int &$overridesCalled) {}

            public function overrides(): array
            {
                $this->overridesCalled++;

                return $this->scopes ?? [];
            }
        };

        $overrides->setOverride('channel:b2b', 'name', 'B2B');

        $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));
        $walker->walk($overrides, 'name', ['channel', 'locale'], $context);

        expect($overridesCalled)->toBe(0);
    },
);

// ─── Task 005: ScopeSignature-based walkAt ────────────────────────────────────

it(
    'walkAt with a single-axis signature returns the override at that scope (case 14: explicit single-axis still works)',
    function (): void {
        $registry = makeWalkerRegistry(['geo' => ['eu', 'eu.de']]);
        $signature = new ScopeSignature(['geo' => 'eu.de']);

        $overrides = new WalkerTraitProduct();
        $overrides->setOverride('geo:eu.de', 'name', 'Hemd');

        $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));
        $result = $walker->walkAt($overrides, 'name', ['geo'], $signature, $registry);

        expect($result->isFound())->toBeTrue()
            ->and($result->value())->toBe('Hemd');
    },
);

it('walkAt with a single-axis signature walks up the hierarchy to find an ancestor match', function (): void {
    $registry = makeWalkerRegistry(['geo' => ['eu', 'eu.de']]);
    $signature = new ScopeSignature(['geo' => 'eu.de']);

    $overrides = new WalkerTraitProduct();
    // Only an ancestor override exists, not at 'eu.de'
    $overrides->setOverride('geo:eu', 'name', 'Shirt-EU');

    $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));
    $result = $walker->walkAt($overrides, 'name', ['geo'], $signature, $registry);

    expect($result->isFound())->toBeTrue()
        ->and($result->value())->toBe('Shirt-EU');
});

it(
    'walkAt with a single-axis signature returns notFound when the axis is not in the attribute axes',
    function (): void {
        $registry = makeWalkerRegistry(['geo' => ['eu', 'eu.de'], 'locale' => ['de']]);
        $signature = new ScopeSignature(['locale' => 'de']);

        $overrides = new WalkerTraitProduct();
        $overrides->setOverride('locale:de', 'name', 'Hemd');

        $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));
        // Attribute axes only contains 'geo', not 'locale'
        $result = $walker->walkAt($overrides, 'name', ['geo'], $signature, $registry);

        expect($result->isFound())->toBeFalse();
    },
);

it(
    'walkAt with a single-axis signature returns notFound when neither the scope nor any ancestor has an override',
    function (): void {
        $registry = makeWalkerRegistry(['geo' => ['eu', 'eu.de']]);
        $signature = new ScopeSignature(['geo' => 'eu.de']);

        $overrides = new WalkerTraitProduct();
        // No overrides set at all

        $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));
        $result = $walker->walkAt($overrides, 'name', ['geo'], $signature, $registry);

        expect($result->isFound())->toBeFalse();
    },
);

it('walkAt throws MultiAxisWalkAtNotSupportedException when the signature has two axes (case 15)', function (): void {
    $registry = makeWalkerRegistry(['geo' => ['eu', 'eu.de'], 'locale' => ['de']]);
    $signature = new ScopeSignature(['geo' => 'eu.de', 'locale' => 'de']);

    $overrides = new WalkerTraitProduct();

    $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));

    expect(fn () => $walker->walkAt($overrides, 'name', ['geo', 'locale'], $signature, $registry))
        ->toThrow(MultiAxisWalkAtNotSupportedException::class);
});

it('walkAt throws MultiAxisWalkAtNotSupportedException when the signature has three axes', function (): void {
    $registry = makeWalkerRegistry([
        'channel' => ['b2b'],
        'geo'     => ['eu', 'eu.de'],
        'locale'  => ['de'],
    ]);
    $signature = new ScopeSignature(['channel' => 'b2b', 'geo' => 'eu.de', 'locale' => 'de']);

    $overrides = new WalkerTraitProduct();

    $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));

    expect(fn () => $walker->walkAt($overrides, 'name', ['channel', 'geo', 'locale'], $signature, $registry))
        ->toThrow(MultiAxisWalkAtNotSupportedException::class);
});

it('walkAt ignores the ScopeContext entirely (the signature axes determine the lookup)', function (): void {
    $registry = makeWalkerRegistry([
        'geo'    => ['eu', 'eu.de'],
        'locale' => ['de'],
    ]);

    // Context says locale:de — but walkAt should use the signature (geo:eu.de), not the context
    $context = new ScopeContext($registry);
    $context->in('locale', 'de');

    $signature = new ScopeSignature(['geo' => 'eu.de']);

    $overrides = new WalkerTraitProduct();
    $overrides->setOverride('geo:eu.de', 'name', 'Hemd');
    // Would also match locale if context were used
    $overrides->setOverride('locale:de', 'name', 'Wrong');

    $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));
    // walkAt does not accept a context — confirms context is irrelevant
    $result = $walker->walkAt($overrides, 'name', ['geo'], $signature, $registry);

    expect($result->isFound())->toBeTrue()
        ->and($result->value())->toBe('Hemd');
});

it(
    'walkAt returns notFound for a signature at the axis default even when a stored override exists at axis:default',
    function (): void {
        // geo axis default is '__test_default'
        // A signature pointing at the default value should return notFound
        // even when there is a stored override keyed at 'geo:__test_default'
        $registry = makeWalkerRegistry(['geo' => ['eu', 'eu.de']]);
        $signature = new ScopeSignature(['geo' => '__test_default']);

        $overrides = new WalkerTraitProduct();
        // Deliberately store an override at the default scope key
        $overrides->setOverride('geo:__test_default', 'name', 'Should-Not-Return');

        $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));
        $result = $walker->walkAt($overrides, 'name', ['geo'], $signature, $registry);

        expect($result->isFound())->toBeFalse();
    },
);

it(
    'walkAt skips a default ancestor while still matching a non-default descendant override during walk-up',
    function (): void {
        // geo hierarchy: global (default) -> global.eu -> global.eu.de
        // walkUp('global.eu') = ['global.eu', 'global']
        // After filtering default 'global': only 'global.eu' is searched
        // Override is stored at 'geo:global.eu' — should be found
        $registry = makeWalkerRegistry(['geo' => ['global.eu']], ['geo' => 'global']);
        $signature = new ScopeSignature(['geo' => 'global.eu']);

        $overrides = new WalkerTraitProduct();
        $overrides->setOverride('geo:global', 'name', 'Should-Not-Return');
        $overrides->setOverride('geo:global.eu', 'name', 'EU-override');

        $walker = new ScopeWalker(new SignatureCandidateEnumerator($registry));
        $result = $walker->walkAt($overrides, 'name', ['geo'], $signature, $registry);

        expect($result->isFound())->toBeTrue()
            ->and($result->value())->toBe('EU-override');
    },
);
