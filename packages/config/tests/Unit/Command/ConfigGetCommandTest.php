<?php

declare(strict_types=1);

use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Markommerce\Config\Attributes\Config;
use Markommerce\Config\Command\ConfigGetCommand;
use Markommerce\Config\Registry\ConfigRegistry;
use Markommerce\Config\Registry\ConfigRegistryBuilder;
use Markommerce\Config\Storage\InMemoryConfigStorage;
use Markommerce\Config\ValueObjects\ConfigRow;

// --- Fixture config classes ---

class GetStringConfig
{
    #[Config(key: 'general/store.name')]
    public string $name = 'default-store';
}

class GetSecretConfig
{
    #[Config(key: 'payment/stripe.secret_key', secret: true)]
    public string $secretKey = '';
}

// --- Helpers ---

function makeGetRegistry(): ConfigRegistry
{
    $builder = new ConfigRegistryBuilder();

    return $builder->build(
        [GetStringConfig::class, GetSecretConfig::class],
    );
}

/**
 * @return array{exitCode: int, output: string}
 */
function runGetCommand(
    ConfigRegistry $registry,
    InMemoryConfigStorage $storage,
    string ...$args,
): array {
    $stream = fopen('php://memory', 'r+');

    assert($stream !== false);

    $cmdArgs = array_merge(['marko', 'config:get'], $args);
    /** @var list<string> $cmdArgs */
    $input = new Input($cmdArgs);
    $output = new Output($stream);

    $command = new ConfigGetCommand($registry, $storage);
    $exitCode = $command->execute($input, $output);

    rewind($stream);

    return [
        'exitCode' => $exitCode,
        'output'   => (string) stream_get_contents($stream),
    ];
}

// --- Tests ---

it('resolves the global value via ConfigGetCommand by calling resolver.resolved directly', function (): void {
    $registry = makeGetRegistry();
    $storage = new InMemoryConfigStorage();

    $storage->compareAndSave('general/store.name', new ConfigRow(
        key: 'general/store.name',
        value: 'My Store',
        version: 0,
    ), 0);

    $result = runGetCommand($registry, $storage, 'general/store.name');

    expect($result['exitCode'])->toBe(0)
        ->and($result['output'])->toContain('My Store');
});

it(
    'does not inject a ScopeRegistryInterface into ConfigGetCommand\'s constructor after task completes',
    function (): void {
        $reflection = new ReflectionClass(ConfigGetCommand::class);
        $constructor = $reflection->getConstructor();

        assert($constructor !== null);

        $paramTypes = array_map(
            fn ($param) => (string) $param->getType(),
            $constructor->getParameters(),
        );

        foreach ($paramTypes as $type) {
            expect($type)->not->toContain('ScopeRegistryInterface');
        }
    },
);

it(
    'does not print decrypted plaintext when the config is #[Config(secret: true)] — instead shows a redacted marker like ***',
    function (): void {
        $registry = makeGetRegistry();
        $storage = new InMemoryConfigStorage();

        $storage->compareAndSave('payment/stripe.secret_key', new ConfigRow(
            key: 'payment/stripe.secret_key',
            value: 'sk_live_supersecretvalue',
            version: 0,
        ), 0);

        $result = runGetCommand($registry, $storage, 'payment/stripe.secret_key');

        expect($result['exitCode'])->toBe(0)
            ->and($result['output'])->toContain('***')
            ->and($result['output'])->not->toContain('sk_live_supersecretvalue');
    },
);

it('exits non-zero with a did-you-mean hint when the key is unknown', function (): void {
    $registry = makeGetRegistry();
    $storage = new InMemoryConfigStorage();

    // Typo: 'general/store.naem' instead of 'general/store.name'
    $result = runGetCommand($registry, $storage, 'general/store.naem');

    expect($result['exitCode'])->toBe(1)
        ->and($result['output'])->toContain("Config key 'general/store.naem' not found")
        ->and($result['output'])->toContain('Did you mean')
        ->and($result['output'])->toContain('general/store.name');
});

it(
    'does not import scope namespaces from any of the touched production files after task completes',
    function (): void {
        $productionFiles = [
            __DIR__ . '/../../../src/ConfigWriter.php',
            __DIR__ . '/../../../src/Contracts/ConfigWriterInterface.php',
            __DIR__ . '/../../../src/Command/SetCommand.php',
            __DIR__ . '/../../../src/Command/UnsetCommand.php',
            __DIR__ . '/../../../src/Command/ConfigGetCommand.php',
            __DIR__ . '/../../../src/Command/ConfigListCommand.php',
        ];

        $scopeNs = 'Markommerce' . '\\' . 'Scope';
        $scopeSig = 'Scope' . 'Signature';
        $scopeCtx = 'Scope' . 'Context';

        foreach ($productionFiles as $file) {
            $contents = (string) file_get_contents($file);
            expect($contents)
                ->not->toContain($scopeNs)
                ->and($contents)->not->toContain($scopeSig)
                ->and($contents)->not->toContain($scopeCtx);
        }
    },
);
