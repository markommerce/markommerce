<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Markommerce\Config\Attributes\Config;
use Markommerce\Config\Command\ConfigListCommand;
use Markommerce\Config\Registry\ConfigRegistry;
use Markommerce\Config\Registry\ConfigRegistryBuilder;
use Markommerce\Config\Tests\Fakes\FakeScopeRegistry;
use Markommerce\Scope\Attributes\Scoped;

// --- Fixture config classes ---

class ListGlobalConfig
{
    #[Config(key: 'catalog/general.name')]
    public string $name = 'default';
}

class ListScopedConfig
{
    #[Config(key: 'catalog/general.description')]
    #[Scoped(axes: ['store', 'website'])]
    public string $description = 'desc';
}

class ListSecretConfig
{
    #[Config(key: 'payment/stripe.api_key', secret: true)]
    public string $apiKey = '';
}

// --- Helpers ---

function makeListRegistry(): ConfigRegistry
{
    $builder = new ConfigRegistryBuilder();

    return $builder->build(
        [ListGlobalConfig::class, ListScopedConfig::class, ListSecretConfig::class],
        new FakeScopeRegistry(['store', 'website']),
    );
}

function runListCommand(ConfigRegistry $registry, string ...$args): string
{
    $stream = fopen('php://memory', 'r+');
    $input = new Input(array_merge(['marko', 'config:list'], $args));
    $output = new Output($stream);

    $command = new ConfigListCommand($registry);
    $command->execute($input, $output);

    rewind($stream);

    return (string) stream_get_contents($stream);
}

// --- Tests ---

it('lists every registered config key as JSON when --format=json is given', function (): void {
    $registry = makeListRegistry();

    $result = runListCommand($registry, '--format=json');

    $decoded = json_decode($result, true);

    expect($decoded)->toBeArray()
        ->and($decoded)->toHaveCount(3);

    $keys = array_column($decoded, 'key');
    expect($keys)->toContain('catalog/general.name')
        ->and($keys)->toContain('catalog/general.description')
        ->and($keys)->toContain('payment/stripe.api_key');

    $descEntry = array_values(array_filter($decoded, fn ($r) => $r['key'] === 'catalog/general.description'))[0];
    expect($descEntry['axes'])->toBe(['store', 'website'])
        ->and($descEntry['source'])->toBe(ListScopedConfig::class);

    $secretEntry = array_values(array_filter($decoded, fn ($r) => $r['key'] === 'payment/stripe.api_key'))[0];
    expect($secretEntry['secret'])->toBeTrue();
});

it('lists every registered config key with its source class and axes in human-readable output', function (): void {
    $registry = makeListRegistry();

    $result = runListCommand($registry);

    expect($result)
        ->toContain('catalog/general.name')
        ->toContain(ListGlobalConfig::class)
        ->toContain('catalog/general.description')
        ->toContain(ListScopedConfig::class)
        ->toContain('store, website')
        ->toContain('payment/stripe.api_key')
        ->toContain(ListSecretConfig::class);
});
