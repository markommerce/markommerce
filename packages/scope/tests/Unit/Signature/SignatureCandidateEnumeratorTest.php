<?php

declare(strict_types=1);

use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Signature\SignatureCandidateEnumerator;

/**
 * @param array<string, list<string>> $axes
 * @param array<string, string> $defaults
 */
function makeRegistry(array $axes = [], array $defaults = []): ScopeRegistryInterface
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

// ─── SignatureCandidateEnumerator ────────────────────────────────────────────

it(
    'returns an empty list when context has no active axes for any declared axis (only the empty signature would be emitted and that is skipped)',
    function (): void {
        $registry = makeRegistry(['channel' => ['b2b', 'b2c'], 'locale' => ['en', 'es']]);
        $context = new ScopeContext($registry);
        // No in() calls — context is empty

        $enumerator = new SignatureCandidateEnumerator($registry);
        $result = $enumerator->enumerate(['channel', 'locale'], $context);

        expect($result)->toBe([]);
    },
);

it('emits a single-axis signature when one axis is declared and context has one value at the root', function (): void {
    $registry = makeRegistry(['channel' => ['b2b', 'b2c']]);
    $context = new ScopeContext($registry);
    $context->in('channel', 'b2b');

    $enumerator = new SignatureCandidateEnumerator($registry);
    $result = $enumerator->enumerate(['channel'], $context);

    expect($result)->toHaveCount(1)
        ->and($result[0]->toString())->toBe('channel:b2b');
});

it(
    'emits walk-up signatures deepest-first when one axis is declared and context value has ancestors',
    function (): void {
        $registry = makeRegistry(['locale' => ['es', 'es.es']]);
        $context = new ScopeContext($registry);
        $context->in('locale', 'es.es');

        $enumerator = new SignatureCandidateEnumerator($registry);
        $result = $enumerator->enumerate(['locale'], $context);

        expect($result)->toHaveCount(2)
            ->and($result[0]->toString())->toBe('locale:es.es')
            ->and($result[1]->toString())->toBe('locale:es');
    },
);

it('emits the cartesian product of walk-up values across two axes', function (): void {
    $registry = makeRegistry([
        'channel' => ['b2b'],
        'locale' => ['es', 'es.es'],
    ]);
    $context = new ScopeContext($registry);
    $context->in('channel', 'b2b')->in('locale', 'es.es');

    $enumerator = new SignatureCandidateEnumerator($registry);
    $result = $enumerator->enumerate(['channel', 'locale'], $context);

    $strings = array_map(fn ($s) => $s->toString(), $result);

    // channel:b2b × [es.es, es] + omit combinations
    expect($strings)->toContain('channel:b2b|locale:es.es')
        ->and($strings)->toContain('channel:b2b|locale:es')
        ->and($strings)->toContain('channel:b2b')
        ->and($strings)->toContain('locale:es.es')
        ->and($strings)->toContain('locale:es');
});

it(
    'emits signatures in descending-score order matching the declared axis priority (case 11: channel:b2b before locale:es.es)',
    function (): void {
        // Case 11: channel path=b2b (root), locale path=es (root)
        // walkUp(b2b) = [b2b], walkUp(es) = [es]
        // Expected order: channel:b2b|locale:es, channel:b2b, locale:es
        $registry = makeRegistry([
            'channel' => ['b2b'],
            'locale' => ['es'],
        ]);
        $context = new ScopeContext($registry);
        $context->in('channel', 'b2b')->in('locale', 'es');

        $enumerator = new SignatureCandidateEnumerator($registry);
        $result = $enumerator->enumerate(['channel', 'locale'], $context);

        $strings = array_map(fn ($s) => $s->toString(), $result);

        expect($strings)->toBe(['channel:b2b|locale:es', 'channel:b2b', 'locale:es']);
    },
);

it('emits the most-specific composite first then progressively less specific ones (case 10)', function (): void {
    // Case 10: [channel, locale], channel=b2b (root), locale=es.es
    // walkUp(b2b) = [b2b], walkUp(es.es) = [es.es, es]
    // Expected order: channel:b2b|locale:es.es, channel:b2b|locale:es, channel:b2b, locale:es.es, locale:es
    $registry = makeRegistry([
        'channel' => ['b2b'],
        'locale' => ['es', 'es.es'],
    ]);
    $context = new ScopeContext($registry);
    $context->in('channel', 'b2b')->in('locale', 'es.es');

    $enumerator = new SignatureCandidateEnumerator($registry);
    $result = $enumerator->enumerate(['channel', 'locale'], $context);

    $strings = array_map(fn ($s) => $s->toString(), $result);

    expect($strings)->toBe([
        'channel:b2b|locale:es.es',
        'channel:b2b|locale:es',
        'channel:b2b',
        'locale:es.es',
        'locale:es',
    ]);
});

it('skips the empty signature (all axes OMIT)', function (): void {
    // When no context is active for any declared axis, the only possible combination
    // would be all-OMIT which results in an empty signature — that gets skipped
    $registry = makeRegistry(['channel' => ['b2b'], 'locale' => ['es']]);
    $context = new ScopeContext($registry);
    // No in() calls — all axes are OMIT

    $enumerator = new SignatureCandidateEnumerator($registry);
    $result = $enumerator->enumerate(['channel', 'locale'], $context);

    // The only candidate would be the all-OMIT empty signature, which is skipped
    expect($result)->toBe([]);
});

it('handles three axes producing correctly ordered partial and full compositions (case 12)', function (): void {
    // Three axes at root level — each has single-value walkUp
    // axes declared in order: [channel, locale, market]
    // Expected: enumerate produces 7 non-empty combinations (2^3 - 1)
    // in descending score / priority order:
    // channel:b2b|locale:es|market:eu
    // channel:b2b|locale:es
    // channel:b2b|market:eu
    // channel:b2b
    // locale:es|market:eu
    // locale:es
    // market:eu
    $registry = makeRegistry([
        'channel' => ['b2b'],
        'locale' => ['es'],
        'market' => ['eu'],
    ]);
    $context = new ScopeContext($registry);
    $context->in('channel', 'b2b')->in('locale', 'es')->in('market', 'eu');

    $enumerator = new SignatureCandidateEnumerator($registry);
    $result = $enumerator->enumerate(['channel', 'locale', 'market'], $context);

    $strings = array_map(fn ($s) => $s->toString(), $result);

    expect($strings)->toBe([
        'channel:b2b|locale:es|market:eu',
        'channel:b2b|locale:es',
        'channel:b2b|market:eu',
        'channel:b2b',
        'locale:es|market:eu',
        'locale:es',
        'market:eu',
    ]);
});

it('caps the candidate list at the configured cap', function (): void {
    // 3 axes at root → 7 candidates normally; cap to 3
    $registry = makeRegistry([
        'channel' => ['b2b'],
        'locale' => ['es'],
        'market' => ['eu'],
    ]);
    $context = new ScopeContext($registry);
    $context->in('channel', 'b2b')->in('locale', 'es')->in('market', 'eu');

    $enumerator = new SignatureCandidateEnumerator($registry, cap: 3);

    // Suppress expected warning
    set_error_handler(fn () => true);
    $result = $enumerator->enumerate(['channel', 'locale', 'market'], $context);
    restore_error_handler();

    expect($result)->toHaveCount(3);
});

it('emits exactly one E_USER_WARNING per enumerate() call when the cap is exceeded', function (): void {
    $registry = makeRegistry([
        'channel' => ['b2b'],
        'locale' => ['es'],
        'market' => ['eu'],
    ]);
    $context = new ScopeContext($registry);
    $context->in('channel', 'b2b')->in('locale', 'es')->in('market', 'eu');

    $enumerator = new SignatureCandidateEnumerator($registry, cap: 3);

    $warningCount = 0;
    set_error_handler(function (int $errno, string $errstr) use (&$warningCount): bool {
        if ($errno === E_USER_WARNING) {
            $warningCount++;
        }

        return true;
    });

    $enumerator->enumerate(['channel', 'locale', 'market'], $context);
    restore_error_handler();

    expect($warningCount)->toBe(1);
});

it('uses the default cap of 256 when no cap is configured', function (): void {
    // Build 9 axes with 1 value each → 2^9 - 1 = 511 candidates > 256
    $axesDef = [];
    for ($i = 0; $i < 9; $i++) {
        $axesDef["axis$i"] = ["val$i"];
    }
    $registry = makeRegistry($axesDef);
    $context = new ScopeContext($registry);
    foreach (array_keys($axesDef) as $axisName) {
        $context->in($axisName, $axesDef[$axisName][0]);
    }

    $enumerator = new SignatureCandidateEnumerator($registry);

    $warningFired = false;
    set_error_handler(function (int $errno) use (&$warningFired): bool {
        if ($errno === E_USER_WARNING) {
            $warningFired = true;
        }

        return true;
    });
    $result = $enumerator->enumerate(array_keys($axesDef), $context);
    restore_error_handler();

    // Default cap is 256, so result must be capped and warning fired
    expect($result)->toHaveCount(256)
        ->and($warningFired)->toBeTrue();
});

it('memoizes results for repeated calls with the same attributeAxes and context state', function (): void {
    $registry = makeRegistry(['channel' => ['b2b']]);
    $context = new ScopeContext($registry);
    $context->in('channel', 'b2b');

    $enumerator = new SignatureCandidateEnumerator($registry);

    $first = $enumerator->enumerate(['channel'], $context);
    $second = $enumerator->enumerate(['channel'], $context);

    // Same object instances (memoized)
    expect($first)->toBe($second);
});

it(
    'returns a fresh list when the context state has changed since the last call (different active path for the same axis set)',
    function (): void {
        $registry = makeRegistry(['channel' => ['b2b', 'b2c']]);
        $context = new ScopeContext($registry);
        $context->in('channel', 'b2b');

        $enumerator = new SignatureCandidateEnumerator($registry);

        $first = $enumerator->enumerate(['channel'], $context);

        // Change context
        $context->in('channel', 'b2c');
        $second = $enumerator->enumerate(['channel'], $context);

        expect($first)->not->toBe($second)
            ->and($first[0]->toString())->toBe('channel:b2b')
            ->and($second[0]->toString())->toBe('channel:b2c');
    },
);

it(
    'returns the same cached list regardless of the order in which axes were added to ScopeContext (cache key is ksorted before serialization)',
    function (): void {
        $registry = makeRegistry(['channel' => ['b2b'], 'locale' => ['es']]);

        // Context 1: channel set first
        $context1 = new ScopeContext($registry);
        $context1->in('channel', 'b2b')->in('locale', 'es');

        // Context 2: locale set first
        $context2 = new ScopeContext($registry);
        $context2->in('locale', 'es')->in('channel', 'b2b');

        $enumerator = new SignatureCandidateEnumerator($registry);

        $result1 = $enumerator->enumerate(['channel', 'locale'], $context1);
        $result2 = $enumerator->enumerate(['channel', 'locale'], $context2);

        // Both should return the same cached result (same object identity)
        expect($result1)->toBe($result2);
    },
);

it(
    'returns ScopeSignature objects with axes alphabetically sorted regardless of declaration order',
    function (): void {
        // Declare axes in non-alphabetical order (z before a before m)
        $registry = makeRegistry([
            'zebra' => ['zval'],
            'apple' => ['aval'],
            'mango' => ['mval'],
        ]);
        $context = new ScopeContext($registry);
        $context->in('zebra', 'zval')->in('apple', 'aval')->in('mango', 'mval');

        $enumerator = new SignatureCandidateEnumerator($registry);
        $result = $enumerator->enumerate(['zebra', 'apple', 'mango'], $context);

        // The full composite signature should have axes alphabetically sorted
        $fullSignature = array_find($result, fn ($s) => count($s->axes()) === 3);
        expect($fullSignature)->not->toBeNull()
            ->and($fullSignature->axes())->toBe(['apple', 'mango', 'zebra'])
            ->and($fullSignature->toString())->toBe('apple:aval|mango:mval|zebra:zval');
    },
);

it(
    'emits OMIT exactly once for an attribute axis not present in context (the axis contributes one OMIT iteration, not zero — composites without that axis still emit)',
    function (): void {
        // channel and locale are in registry. Only channel is active in context.
        // locale is declared in attributeAxes but has no active context value.
        // Expected: the locale contributes one OMIT iteration → only channel:b2b emits
        $registry = makeRegistry(['channel' => ['b2b'], 'locale' => ['es']]);
        $context = new ScopeContext($registry);
        $context->in('channel', 'b2b');
        // locale not set

        $enumerator = new SignatureCandidateEnumerator($registry);
        $result = $enumerator->enumerate(['channel', 'locale'], $context);

        $strings = array_map(fn ($s) => $s->toString(), $result);

        // locale is OMIT → no signatures mentioning locale, only channel:b2b
        expect($strings)->toBe(['channel:b2b']);
    },
);

it('returns an empty list when no axes are declared', function (): void {
    $registry = makeRegistry(['channel' => ['b2b', 'b2c']]);
    $context = new ScopeContext($registry);
    $context->in('channel', 'b2b');

    $enumerator = new SignatureCandidateEnumerator($registry);
    $result = $enumerator->enumerate([], $context);

    expect($result)->toBe([]);
});

it('returns an empty list for a single-axis attribute resolved to its default', function (): void {
    // Single axis channel at its default value
    // After filtering, channel is OMIT-only → empty list
    $registry = makeRegistry(['channel' => ['b2b']]);
    $context = new ScopeContext($registry);
    $context->in('channel', '__test_default');

    $enumerator = new SignatureCandidateEnumerator($registry);
    $result = $enumerator->enumerate(['channel'], $context);

    expect($result)->toBe([]);
});

it('enumerates partial composites when some axes are default and others are not', function (): void {
    // channel is at its default value (__test_default), locale is at a non-default value
    // Only locale-based candidates should appear (channel is omitted)
    $registry = makeRegistry(['channel' => ['b2b'], 'locale' => ['es']]);
    $context = new ScopeContext($registry);
    $context->in('channel', '__test_default')->in('locale', 'es');

    $enumerator = new SignatureCandidateEnumerator($registry);
    $result = $enumerator->enumerate(['channel', 'locale'], $context);

    $strings = array_map(fn ($s) => $s->toString(), $result);
    // channel is at default → filtered → only locale:es remains
    expect($strings)->toBe(['locale:es']);
});

it('enumerates candidates normally for axes at non-default scopes', function (): void {
    // When both axes are at non-default values, enumeration should work as usual
    $registry = makeRegistry(['channel' => ['b2b'], 'locale' => ['es']]);
    $context = new ScopeContext($registry);
    $context->in('channel', 'b2b')->in('locale', 'es');

    $enumerator = new SignatureCandidateEnumerator($registry);
    $result = $enumerator->enumerate(['channel', 'locale'], $context);

    $strings = array_map(fn ($s) => $s->toString(), $result);
    expect($strings)->toBe(['channel:b2b|locale:es', 'channel:b2b', 'locale:es']);
});

it('returns an empty candidate list when every attribute axis is at its default', function (): void {
    // Both channel and locale are set to their default values
    // After filtering defaults, both axes are OMIT-only → no candidates
    $registry = makeRegistry(['channel' => ['b2b'], 'locale' => ['es']]);
    $context = new ScopeContext($registry);
    $context->in('channel', '__test_default')->in('locale', '__test_default');

    $enumerator = new SignatureCandidateEnumerator($registry);
    $result = $enumerator->enumerate(['channel', 'locale'], $context);

    expect($result)->toBe([]);
});

it('filters the default scope out of hierarchy walk-up results', function (): void {
    // Hierarchy: global (default) -> global.eu -> global.eu.de
    // walkUp('global.eu') yields ['global.eu', 'global']
    // After filter (remove default 'global'): only 'global.eu' remains
    $registry = makeRegistry(['geo' => ['global.eu']], ['geo' => 'global']);
    $context = new ScopeContext($registry);
    $context->in('geo', 'global.eu');

    $enumerator = new SignatureCandidateEnumerator($registry);
    $result = $enumerator->enumerate(['geo'], $context);

    // Only geo:global.eu should be present — global (the default) is filtered out
    $strings = array_map(fn ($s) => $s->toString(), $result);
    expect($strings)->toBe(['geo:global.eu']);
});

it('omits an axis whose context value equals the axis default scope', function (): void {
    // channel's default is '__test_default'; context is set to that default value
    // The axis should be treated as OMIT-only (no candidates should include channel)
    $registry = makeRegistry(['channel' => ['b2b']]);
    $context = new ScopeContext($registry);
    $context->in('channel', '__test_default');

    $enumerator = new SignatureCandidateEnumerator($registry);
    $result = $enumerator->enumerate(['channel'], $context);

    expect($result)->toBe([]);
});
