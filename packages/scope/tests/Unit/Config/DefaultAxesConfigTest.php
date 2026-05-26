<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Markommerce\Scope\Registry\PhpScopeRegistry;

function makeConfigStubForDefaultAxes(array $axes): ConfigRepositoryInterface
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

$config = require dirname(__DIR__, 3) . '/config/scope.php';

it('declares only market and channel axes in scope\'s default config (no locale)', function () use ($config): void {
    expect($config['axes'])->toHaveKeys(['market', 'channel'])
        ->and($config['axes'])->not->toHaveKey('locale');
});

it('gives the market axis a single scope named default set as its default', function () use ($config): void {
    expect($config['axes']['market']['default'])->toBe('default')
        ->and($config['axes']['market']['scopes'])->toHaveKey('default');
});

it('gives the channel axis a single scope named web set as its default', function () use ($config): void {
    expect($config['axes']['channel']['default'])->toBe('web')
        ->and($config['axes']['channel']['scopes'])->toHaveKey('web');
});

it('is accepted by PhpScopeRegistry and lists only [market, channel] axes from scope\'s default config', function () use ($config): void {
    $stub = makeConfigStubForDefaultAxes($config['axes']);
    $registry = new PhpScopeRegistry($stub);

    expect($registry->listAxes())->toBe(['market', 'channel']);
});

it('it removes the obsolete CatalogMetadataIntegrationTest file that cross-referenced catalog entities from scope tests', function (): void {
    $obsoleteFile = dirname(__DIR__, 2) . '/Feature/CatalogMetadataIntegrationTest.php';

    expect(file_exists($obsoleteFile))->toBeFalse();
});
