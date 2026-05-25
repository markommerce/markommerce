<?php

declare(strict_types=1);

use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Markommerce\Config\Attributes\Config;
use Markommerce\Config\Command\UnsetCommand;
use Markommerce\Config\Encryption\NullSecretCipher;
use Markommerce\Config\Exceptions\AxisNotDeclaredException;
use Markommerce\Config\Exceptions\StaleConfigWriteException;
use Markommerce\Config\Registry\ConfigRegistry;
use Markommerce\Config\Registry\ConfigRegistryBuilder;
use Markommerce\Config\Storage\InMemoryConfigStorage;
use Markommerce\Config\Tests\Fakes\FakeScopeRegistry;
use Markommerce\Config\ConfigWriter;
use Markommerce\Scope\Attributes\Scoped;
use Markommerce\Scope\Signature\ScopeSignature;

// --- Fixture config classes ---

class UnsetCommandStringConfig
{
    #[Config(key: 'cli/unset.string_val')]
    public string $stringVal = 'default';
}

class UnsetCommandScopedConfig
{
    #[Config(key: 'cli/unset.scoped_val')]
    #[Scoped(axes: ['store', 'website'])]
    public string $scopedVal = 'default';
}

// --- Helpers ---

function buildUnsetCommandRegistry(): ConfigRegistry
{
    $builder = new ConfigRegistryBuilder();

    return $builder->build(
        [
            UnsetCommandStringConfig::class,
            UnsetCommandScopedConfig::class,
        ],
        new FakeScopeRegistry(['store', 'website']),
    );
}

function buildUnsetCommand(InMemoryConfigStorage $storage): UnsetCommand
{
    $registry = buildUnsetCommandRegistry();
    $writer = new ConfigWriter(
        registry: $registry,
        storage: $storage,
        cipher: new NullSecretCipher(),
    );

    return new UnsetCommand(
        registry: $registry,
        writer: $writer,
    );
}

function makeUnsetInput(string ...$args): Input
{
    return new Input(['marko', 'config:unset', ...$args]);
}

function captureUnsetOutput(UnsetCommand $command, Input $input): array
{
    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);
    $exitCode = $command->execute($input, $output);
    rewind($stream);
    $text = stream_get_contents($stream);
    fclose($stream);

    return ['exitCode' => $exitCode, 'output' => $text];
}

// --- Tests ---

it('clears the global value via config:unset without --scope', function (): void {
    $storage = new InMemoryConfigStorage();
    $registry = buildUnsetCommandRegistry();
    $writer = new ConfigWriter(
        registry: $registry,
        storage: $storage,
        cipher: new NullSecretCipher(),
    );
    // Pre-seed a global value
    $writer->setGlobal('cli/unset.string_val', 'set-value');

    $command = new UnsetCommand(registry: $registry, writer: $writer);
    $input = makeUnsetInput('cli/unset.string_val');
    $result = captureUnsetOutput($command, $input);

    expect($result['exitCode'])->toBe(0);

    // Row should be gone since no overrides exist
    expect($storage->load('cli/unset.string_val'))->toBeNull();
});

it('clears a specific scoped override via config:unset with --scope', function (): void {
    $storage = new InMemoryConfigStorage();
    $registry = buildUnsetCommandRegistry();
    $writer = new ConfigWriter(
        registry: $registry,
        storage: $storage,
        cipher: new NullSecretCipher(),
    );

    // Pre-seed two scoped overrides
    $writer->setOverride('cli/unset.scoped_val', new ScopeSignature(['store' => '1']), 'store-1');
    $writer->setOverride('cli/unset.scoped_val', new ScopeSignature(['store' => '2']), 'store-2');

    $command = new UnsetCommand(registry: $registry, writer: $writer);
    $input = makeUnsetInput('cli/unset.scoped_val', '--scope=store=1');
    $result = captureUnsetOutput($command, $input);

    expect($result['exitCode'])->toBe(0);

    $row = $storage->load('cli/unset.scoped_val');
    expect($row)->not->toBeNull()
        ->and($row->overrides)->not->toHaveKey('store:1')
        ->and($row->overrides)->toHaveKey('store:2')
        ->and($row->overrides['store:2'])->toBe('store-2');
});
