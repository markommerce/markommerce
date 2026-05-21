<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Exceptions\ScopeConfigurationException;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Registry\PhpScopeRegistry;

function makeConfigStub(array $axes): ConfigRepositoryInterface
{
    return new readonly class ($axes) implements ConfigRepositoryInterface
    {
        public function __construct(private readonly array $axes) {}

        public function get(
            string $key,
            ?string $scope = null,
        ): mixed {
            return $this->axes;
        }

        public function has(
            string $key,
            ?string $scope = null,
        ): bool {
            return true;
        }

        public function getString(
            string $key,
            ?string $scope = null,
        ): string {
            return '';
        }

        public function getInt(
            string $key,
            ?string $scope = null,
        ): int {
            return 0;
        }

        public function getBool(
            string $key,
            ?string $scope = null,
        ): bool {
            return false;
        }

        public function getFloat(
            string $key,
            ?string $scope = null,
        ): float {
            return 0.0;
        }

        public function getArray(
            string $key,
            ?string $scope = null,
        ): array {
            return $this->axes;
        }

        public function all(?string $scope = null): array
        {
            return $this->axes;
        }

        public function withScope(string $scope): ConfigRepositoryInterface
        {
            return $this;
        }
    };
}

it('builds an axis from a scopes map keyed by scope path', function (): void {
    $config = makeConfigStub([
        'geo' => [
            'default' => 'eu',
            'scopes' => ['eu' => [], 'eu.de' => [], 'us' => []],
        ],
    ]);

    $registry = new PhpScopeRegistry($config);

    expect($registry->getAxis('geo'))->toBeInstanceOf(ScopeAxis::class)
        ->and($registry->getAxis('geo')->name)->toBe('geo');
});

it('preserves scope declaration order when building the hierarchy', function (): void {
    $config = makeConfigStub([
        'geo' => [
            'default' => 'eu',
            'scopes' => ['eu' => [], 'eu.de' => [], 'us' => []],
        ],
    ]);

    $registry = new PhpScopeRegistry($config);

    expect($registry->getAxis('geo')->hierarchy->paths())->toBe(['eu', 'eu.de', 'us']);
});

it('assigns the configured default scope to the built axis', function (): void {
    $config = makeConfigStub([
        'geo' => [
            'default' => 'eu',
            'scopes' => ['eu' => [], 'eu.de' => [], 'us' => []],
        ],
    ]);

    $registry = new PhpScopeRegistry($config);

    expect($registry->getAxis('geo')->default)->toBe('eu');
});

it('throws ScopeConfigurationException when an axis omits the default key', function (): void {
    $config = makeConfigStub([
        'geo' => [
            'scopes' => ['eu' => [], 'us' => []],
        ],
    ]);

    expect(fn () => new PhpScopeRegistry($config))->toThrow(ScopeConfigurationException::class);
});

it('throws ScopeConfigurationException when the default is not a key in the scopes map', function (): void {
    $config = makeConfigStub([
        'geo' => [
            'default' => 'global',
            'scopes' => ['eu' => [], 'us' => []],
        ],
    ]);

    expect(fn () => new PhpScopeRegistry($config))->toThrow(ScopeConfigurationException::class);
});

it('throws ScopeConfigurationException when the scopes map is empty', function (): void {
    $config = makeConfigStub([
        'geo' => [
            'default' => 'eu',
            'scopes' => [],
        ],
    ]);

    expect(fn () => new PhpScopeRegistry($config))->toThrow(ScopeConfigurationException::class);
});

it('throws ScopeConfigurationException when scopes is not an array', function (): void {
    $config = makeConfigStub([
        'geo' => [
            'default' => 'eu',
            'scopes' => 'not-an-array',
        ],
    ]);

    expect(fn () => new PhpScopeRegistry($config))->toThrow(ScopeConfigurationException::class);
});

it('accepts an empty top-level axes array without error', function (): void {
    $config = makeConfigStub([]);

    $registry = new PhpScopeRegistry($config);

    expect($registry->listAxes())->toBe([]);
});

it('throws UnknownAxisException when getAxis is called with unknown axis', function (): void {
    $config = makeConfigStub([
        'geo' => [
            'default' => 'eu',
            'scopes' => ['eu' => [], 'eu.de' => []],
        ],
    ]);

    $registry = new PhpScopeRegistry($config);

    expect(fn () => $registry->getAxis('unknown'))->toThrow(UnknownAxisException::class);
});

it('throws ScopeConfigurationException when config shape is malformed', function (): void {
    $config = makeConfigStub([
        'geo' => 'not-an-array',
    ]);

    expect(fn () => new PhpScopeRegistry($config))->toThrow(ScopeConfigurationException::class);
});

it('returns the list of all registered axis names in registration order', function (): void {
    $config = makeConfigStub([
        'geo' => ['default' => 'eu', 'scopes' => ['eu' => [], 'us' => []]],
        'locale' => ['default' => 'en', 'scopes' => ['en' => [], 'fr' => []]],
        'channel' => ['default' => 'web', 'scopes' => ['web' => [], 'mobile' => []]],
    ]);

    $registry = new PhpScopeRegistry($config);

    expect($registry->listAxes())->toBe(['geo', 'locale', 'channel']);
});
