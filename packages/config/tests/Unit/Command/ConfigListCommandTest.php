<?php

declare(strict_types=1);

use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Markommerce\Config\Attributes\Config;
use Markommerce\Config\Command\ConfigListCommand;
use Markommerce\Config\Registry\ConfigRegistry;
use Markommerce\Config\Registry\ConfigRegistryBuilder;

// --- Fixture config classes ---

class ListGlobalConfig
{
    #[Config(key: 'catalog/general.name')]
    public string $name = 'default';
}

class ListAnotherConfig
{
    #[Config(key: 'catalog/general.description')]
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
        [ListGlobalConfig::class, ListAnotherConfig::class, ListSecretConfig::class],
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

    $secretEntry = array_values(array_filter($decoded, fn ($r) => $r['key'] === 'payment/stripe.api_key'))[0];
    expect($secretEntry['secret'])->toBeTrue();
});

it('lists every registered config key with its source class in human-readable output', function (): void {
    $registry = makeListRegistry();

    $result = runListCommand($registry);

    expect($result)
        ->toContain('catalog/general.name')
        ->toContain(ListGlobalConfig::class)
        ->toContain('catalog/general.description')
        ->toContain(ListAnotherConfig::class)
        ->toContain('payment/stripe.api_key')
        ->toContain(ListSecretConfig::class);
});
