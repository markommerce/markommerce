<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Core\Container\ContainerInterface;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Exceptions\InvalidResolverConfigException;
use Markommerce\Scope\Resolver\Resolution\ScopeAxisResolverInterface;
use Markommerce\Scope\Resolver\Resolution\ScopeResolutionContext;
use Markommerce\Scope\Resolver\Resolution\ScopeResolverChainFactory;

// ─── Fakes ───────────────────────────────────────────────────────────────────

class FakeResolverA implements ScopeAxisResolverInterface
{
    public function resolve(ScopeAxis $scopeAxis, ScopeResolutionContext $scopeResolutionContext): ?string
    {
        return null;
    }
}

class FakeResolverB implements ScopeAxisResolverInterface
{
    public function resolve(ScopeAxis $scopeAxis, ScopeResolutionContext $scopeResolutionContext): ?string
    {
        return null;
    }
}

class FakeResolverWithArgs implements ScopeAxisResolverInterface
{
    public function __construct(public readonly string $cookieName) {}

    public function resolve(ScopeAxis $scopeAxis, ScopeResolutionContext $scopeResolutionContext): ?string
    {
        return null;
    }
}

class NotAResolver
{
    // Does NOT implement ScopeAxisResolverInterface
}

/**
 * @param array<string, mixed> $config  keyed by config-key
 */
function makeChainFactoryFakes(array $config = []): array
{
    $container = new class () implements ContainerInterface
    {
        /** @var array<string, object> */
        private array $bindings = [];

        public function bind(string $id, object $instance): void
        {
            $this->bindings[$id] = $instance;
        }

        public function get(string $id): mixed
        {
            if (isset($this->bindings[$id])) {
                return $this->bindings[$id];
            }

            if (class_exists($id)) {
                return new $id();
            }

            throw new RuntimeException("Container: class $id not found");
        }

        public function has(string $id): bool
        {
            return isset($this->bindings[$id]) || class_exists($id);
        }

        public function singleton(string $id): void {}

        public function instance(string $id, object $instance): void
        {
            $this->bindings[$id] = $instance;
        }

        public function call(Closure $callable): mixed
        {
            return $callable();
        }
    };

    $configRepository = new class ($config) implements ConfigRepositoryInterface
    {
        /** @param array<string, mixed> $config */
        public function __construct(private readonly array $config) {}

        public function get(string $key, ?string $scope = null): mixed
        {
            if (!array_key_exists($key, $this->config)) {
                throw new ConfigNotFoundException("Key '$key' not found");
            }

            return $this->config[$key];
        }

        public function has(string $key, ?string $scope = null): bool
        {
            return array_key_exists($key, $this->config);
        }

        public function getString(string $key, ?string $scope = null): string
        {
            return (string) $this->get($key);
        }

        public function getInt(string $key, ?string $scope = null): int
        {
            return (int) $this->get($key);
        }

        public function getBool(string $key, ?string $scope = null): bool
        {
            return (bool) $this->get($key);
        }

        public function getFloat(string $key, ?string $scope = null): float
        {
            return (float) $this->get($key);
        }

        public function getArray(string $key, ?string $scope = null): array
        {
            return (array) $this->get($key);
        }

        public function all(?string $scope = null): array
        {
            return $this->config;
        }

        public function withScope(string $scope): ConfigRepositoryInterface
        {
            return $this;
        }
    };

    return [$container, $configRepository];
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('returns empty chain when axis config has no resolvers key', function (): void {
    [$container, $configRepository] = makeChainFactoryFakes([
        // 'scope.axes.store.resolvers' key is absent
    ]);

    $factory = new ScopeResolverChainFactory($container, $configRepository);
    $chain = $factory->for('store');

    expect($chain)->toBe([]);
});

it('returns empty chain when axis resolvers key is empty array', function (): void {
    [$container, $configRepository] = makeChainFactoryFakes([
        'scope.axes.store.resolvers' => [],
    ]);

    $factory = new ScopeResolverChainFactory($container, $configRepository);
    $chain = $factory->for('store');

    expect($chain)->toBe([]);
});

it('builds resolver from bare class-string entry', function (): void {
    [$container, $configRepository] = makeChainFactoryFakes([
        'scope.axes.store.resolvers' => [FakeResolverA::class],
    ]);

    $factory = new ScopeResolverChainFactory($container, $configRepository);
    $chain = $factory->for('store');

    expect($chain)->toHaveCount(1);
    expect($chain[0])->toBeInstanceOf(FakeResolverA::class);
});

it('builds resolver from array entry with class key and named arguments', function (): void {
    [$container, $configRepository] = makeChainFactoryFakes([
        'scope.axes.store.resolvers' => [
            ['class' => FakeResolverWithArgs::class, 'cookieName' => 'store_scope'],
        ],
    ]);

    $factory = new ScopeResolverChainFactory($container, $configRepository);
    $chain = $factory->for('store');

    expect($chain)->toHaveCount(1);
    expect($chain[0])->toBeInstanceOf(FakeResolverWithArgs::class);
    expect($chain[0]->cookieName)->toBe('store_scope');
});

it('caches built chains so for is idempotent per axis', function (): void {
    [$container, $configRepository] = makeChainFactoryFakes([
        'scope.axes.store.resolvers' => [FakeResolverA::class],
    ]);

    $factory = new ScopeResolverChainFactory($container, $configRepository);
    $chain1 = $factory->for('store');
    $chain2 = $factory->for('store');

    expect($chain1)->toBe($chain2); // same reference (cached)
});

it('throws InvalidResolverConfigException unknownClass when class string does not exist', function (): void {
    [$container, $configRepository] = makeChainFactoryFakes([
        'scope.axes.store.resolvers' => ['NonExistentClass\DoesNotExist'],
    ]);

    $factory = new ScopeResolverChainFactory($container, $configRepository);

    expect(fn () => $factory->for('store'))
        ->toThrow(InvalidResolverConfigException::class);
});

it('throws InvalidResolverConfigException missingClassKey when array entry lacks a class key', function (): void {
    [$container, $configRepository] = makeChainFactoryFakes([
        'scope.axes.store.resolvers' => [
            ['cookieName' => 'store_scope'], // missing 'class' key
        ],
    ]);

    $factory = new ScopeResolverChainFactory($container, $configRepository);

    expect(fn () => $factory->for('store'))
        ->toThrow(InvalidResolverConfigException::class);
});

it('throws InvalidResolverConfigException notImplementingInterface when class does not implement ScopeAxisResolverInterface', function (): void {
    [$container, $configRepository] = makeChainFactoryFakes([
        'scope.axes.store.resolvers' => [NotAResolver::class],
    ]);

    $factory = new ScopeResolverChainFactory($container, $configRepository);

    expect(fn () => $factory->for('store'))
        ->toThrow(InvalidResolverConfigException::class);
});

it('preserves resolver order from the config array', function (): void {
    [$container, $configRepository] = makeChainFactoryFakes([
        'scope.axes.store.resolvers' => [
            FakeResolverA::class,
            FakeResolverB::class,
        ],
    ]);

    $factory = new ScopeResolverChainFactory($container, $configRepository);
    $chain = $factory->for('store');

    expect($chain)->toHaveCount(2);
    expect($chain[0])->toBeInstanceOf(FakeResolverA::class);
    expect($chain[1])->toBeInstanceOf(FakeResolverB::class);
});
