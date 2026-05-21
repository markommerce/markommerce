<?php

declare(strict_types=1);

use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Exceptions\InvalidSignatureForAttributeException;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Signature\ScopeSignature;
use Markommerce\Scope\Signature\ScopeSignatureValidator;

// ─── Helpers ─────────────────────────────────────────────────────────────────

class InstrumentedRegistry implements ScopeRegistryInterface
{
    public int $getHierarchyCallCount = 0;

    /** @var array<string, ScopeHierarchy> */
    private array $hierarchies;

    /** @var array<string, string> */
    private array $axisDefaults;

    /**
     * @param array<string, ScopeHierarchy> $axisHierarchies
     * @param array<string, string> $axisDefaults
     */
    public function __construct(array $axisHierarchies, array $axisDefaults = [])
    {
        $this->hierarchies = $axisHierarchies;
        $this->axisDefaults = $axisDefaults;
    }

    public function hasAxis(string $name): bool
    {
        return array_key_exists($name, $this->hierarchies);
    }

    public function getAxis(string $name): ScopeAxis
    {
        $default = $this->axisDefaults[$name] ?? '__test_default';
        $hierarchy = $this->hierarchies[$name];

        if (!$hierarchy->exists($default)) {
            $hierarchy = new ScopeHierarchy(array_merge([$default], $hierarchy->paths()));
        }

        return new ScopeAxis(name: $name, hierarchy: $hierarchy, default: $default);
    }

    public function listAxes(): array
    {
        return array_keys($this->hierarchies);
    }

    public function getHierarchy(string $axisName): ScopeHierarchy
    {
        $this->getHierarchyCallCount++;

        return $this->hierarchies[$axisName];
    }
}

/**
 * @param array<string, ScopeHierarchy> $axisHierarchies
 * @param array<string, string> $axisDefaults
 */
function makeSignatureValidatorRegistry(array $axisHierarchies, array $axisDefaults = []): ScopeRegistryInterface
{
    return new class ($axisHierarchies, $axisDefaults) implements ScopeRegistryInterface
    {
        /** @var array<string, ScopeHierarchy> */
        private array $hierarchies;

        /** @var array<string, string> */
        private array $axisDefaults;

        /**
         * @param array<string, ScopeHierarchy> $axisHierarchies
         * @param array<string, string> $axisDefaults
         */
        public function __construct(array $axisHierarchies, array $axisDefaults = [])
        {
            $this->hierarchies = $axisHierarchies;
            $this->axisDefaults = $axisDefaults;
        }

        public function hasAxis(string $name): bool
        {
            return array_key_exists($name, $this->hierarchies);
        }

        public function getAxis(string $name): ScopeAxis
        {
            $default = $this->axisDefaults[$name] ?? '__test_default';
            $hierarchy = $this->hierarchies[$name];

            if (!$hierarchy->exists($default)) {
                $hierarchy = new ScopeHierarchy(array_merge([$default], $hierarchy->paths()));
            }

            return new ScopeAxis(name: $name, hierarchy: $hierarchy, default: $default);
        }

        public function listAxes(): array
        {
            return array_keys($this->hierarchies);
        }

        public function getHierarchy(string $axisName): ScopeHierarchy
        {
            return $this->hierarchies[$axisName];
        }
    };
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('accepts a signature whose axes are a subset of the attribute axes', function (): void {
    $registry = makeSignatureValidatorRegistry([
        'channel' => new ScopeHierarchy(['b2b', 'b2c']),
        'locale' => new ScopeHierarchy(['en', 'es']),
    ]);
    $validator = new ScopeSignatureValidator($registry);
    $signature = new ScopeSignature(['channel' => 'b2b']);

    expect(fn () => $validator->validate($signature, ['channel', 'locale']))->not->toThrow(Throwable::class);
});

it('accepts a signature equal to the full attribute axes set', function (): void {
    $registry = makeSignatureValidatorRegistry([
        'channel' => new ScopeHierarchy(['b2b', 'b2c']),
        'locale' => new ScopeHierarchy(['en', 'es']),
    ]);
    $validator = new ScopeSignatureValidator($registry);
    $signature = new ScopeSignature(['channel' => 'b2b', 'locale' => 'es']);

    expect(fn () => $validator->validate($signature, ['channel', 'locale']))->not->toThrow(Throwable::class);
});

it(
    'throws InvalidSignatureForAttributeException when the signature mentions an axis not in the attribute axes (case 16)',
    function (): void {
        $registry = makeSignatureValidatorRegistry([
            'channel' => new ScopeHierarchy(['b2b', 'b2c']),
            'market' => new ScopeHierarchy(['eu', 'us']),
        ]);
        $validator = new ScopeSignatureValidator($registry);
        $signature = new ScopeSignature(['market' => 'eu']);

        expect(fn () => $validator->validate($signature, ['channel']))->toThrow(
            InvalidSignatureForAttributeException::class,
        );
    },
);

it(
    'throws InvalidSignatureForAttributeException when the signature value does not exist in the registry hierarchy for that axis (case 17)',
    function (): void {
        $registry = makeSignatureValidatorRegistry([
            'channel' => new ScopeHierarchy(['b2b', 'b2c']),
        ]);
        $validator = new ScopeSignatureValidator($registry);
        $signature = new ScopeSignature(['channel' => 'unknown-value']);

        expect(fn () => $validator->validate($signature, ['channel']))->toThrow(
            InvalidSignatureForAttributeException::class,
        );
    },
);

it(
    'includes the offending axis name in the exception message when the axis is not in attribute axes',
    function (): void {
        $registry = makeSignatureValidatorRegistry([
            'channel' => new ScopeHierarchy(['b2b', 'b2c']),
            'market' => new ScopeHierarchy(['eu', 'us']),
        ]);
        $validator = new ScopeSignatureValidator($registry);
        $signature = new ScopeSignature(['market' => 'eu']);

        try {
            $validator->validate($signature, ['channel']);
            expect(false)->toBeTrue('Expected InvalidSignatureForAttributeException was not thrown');
        } catch (InvalidSignatureForAttributeException $e) {
            expect($e->getMessage())->toContain('market');
        }
    },
);

it(
    'includes the offending value and axis in the exception message when the value is not in hierarchy',
    function (): void {
        $registry = makeSignatureValidatorRegistry([
            'channel' => new ScopeHierarchy(['b2b', 'b2c']),
        ]);
        $validator = new ScopeSignatureValidator($registry);
        $signature = new ScopeSignature(['channel' => 'no-such-channel']);

        try {
            $validator->validate($signature, ['channel']);
            expect(false)->toBeTrue('Expected InvalidSignatureForAttributeException was not thrown');
        } catch (InvalidSignatureForAttributeException $e) {
            expect($e->getMessage())
                ->toContain('no-such-channel')
                ->and($e->getMessage())->toContain('channel');
        }
    },
);

it(
    'memoizes successful validations so the second call does not re-check the registry (verified via instrumented registry)',
    function (): void {
        $registry = new InstrumentedRegistry([
            'channel' => new ScopeHierarchy(['b2b', 'b2c']),
        ]);
        $validator = new ScopeSignatureValidator($registry);
        $signature = new ScopeSignature(['channel' => 'b2b']);

        $validator->validate($signature, ['channel']);
        $callsAfterFirst = $registry->getHierarchyCallCount;

        $validator->validate($signature, ['channel']);
        $callsAfterSecond = $registry->getHierarchyCallCount;

        expect($callsAfterSecond)->toBe($callsAfterFirst);
    },
);

it('does not cache failures (a failing validation re-throws on repeat)', function (): void {
    $registry = makeSignatureValidatorRegistry([
        'channel' => new ScopeHierarchy(['b2b', 'b2c']),
    ]);
    $validator = new ScopeSignatureValidator($registry);
    $signature = new ScopeSignature(['channel' => 'unknown-value']);

    expect(fn () => $validator->validate($signature, ['channel']))->toThrow(
        InvalidSignatureForAttributeException::class,
    );

    expect(fn () => $validator->validate($signature, ['channel']))->toThrow(
        InvalidSignatureForAttributeException::class,
    );
});

it('rejects a single-axis signature naming the axis at its default scope', function (): void {
    $registry = makeSignatureValidatorRegistry(
        ['channel' => new ScopeHierarchy(['b2c', 'b2b'])],
        ['channel' => 'b2c'],
    );
    $validator = new ScopeSignatureValidator($registry);
    $signature = new ScopeSignature(['channel' => 'b2c']);

    expect(fn () => $validator->validate($signature, ['channel']))->toThrow(
        InvalidSignatureForAttributeException::class,
    );
});

it('rejects a default-scope axis inside a multi-axis composite signature', function (): void {
    $registry = makeSignatureValidatorRegistry(
        [
            'channel' => new ScopeHierarchy(['b2c', 'b2b']),
            'locale' => new ScopeHierarchy(['en', 'es']),
        ],
        ['locale' => 'en'],
    );
    $validator = new ScopeSignatureValidator($registry);
    $signature = new ScopeSignature(['channel' => 'b2b', 'locale' => 'en']);

    expect(fn () => $validator->validate($signature, ['channel', 'locale']))->toThrow(
        InvalidSignatureForAttributeException::class,
    );
});

it('accepts a signature naming an axis at a non-default scope', function (): void {
    $registry = makeSignatureValidatorRegistry(
        ['channel' => new ScopeHierarchy(['b2c', 'b2b'])],
        ['channel' => 'b2c'],
    );
    $validator = new ScopeSignatureValidator($registry);
    $signature = new ScopeSignature(['channel' => 'b2b']);

    expect(fn () => $validator->validate($signature, ['channel']))->not->toThrow(Throwable::class);
});

it('accepts a partial signature that omits an axis entirely', function (): void {
    $registry = makeSignatureValidatorRegistry(
        [
            'channel' => new ScopeHierarchy(['b2c', 'b2b']),
            'locale' => new ScopeHierarchy(['en', 'es']),
        ],
        ['channel' => 'b2c', 'locale' => 'en'],
    );
    $validator = new ScopeSignatureValidator($registry);
    // Omits 'locale' entirely — this is a valid partial/composite signature
    $signature = new ScopeSignature(['channel' => 'b2b']);

    expect(fn () => $validator->validate($signature, ['channel', 'locale']))->not->toThrow(Throwable::class);
});
