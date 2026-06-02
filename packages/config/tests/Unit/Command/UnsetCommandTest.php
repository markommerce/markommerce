<?php

declare(strict_types=1);

use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Markommerce\Config\Attributes\Config;
use Markommerce\Config\Command\UnsetCommand;
use Markommerce\Config\ConfigWriter;
use Markommerce\Config\Encryption\NullSecretCipher;
use Markommerce\Config\Registry\ConfigRegistry;
use Markommerce\Config\Registry\ConfigRegistryBuilder;
use Markommerce\Config\Storage\InMemoryConfigStorage;

// --- Fixture config classes ---

class UnsetCommandStringConfig
{
    #[Config(key: 'cli/unset.string_val')]
    public string $stringVal = 'default';
}

// --- Helpers ---

function buildUnsetCommandRegistry(): ConfigRegistry
{
    $builder = new ConfigRegistryBuilder();

    return $builder->build(
        [
            UnsetCommandStringConfig::class,
        ],
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

it('unsets a global value via the UnsetCommand without parsing any scope option', function (): void {
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

it('ignores any --scope option passed to UnsetCommand execute and treats the call as a global unset', function (): void {
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
    // Pass --scope option — must be silently ignored and treated as a global unset
    $input = makeUnsetInput('cli/unset.string_val', '--scope=store=1');
    $result = captureUnsetOutput($command, $input);

    expect($result['exitCode'])->toBe(0);

    // Row should be gone (global unset)
    expect($storage->load('cli/unset.string_val'))->toBeNull();
});
