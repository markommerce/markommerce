<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Attributes\Preference;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Markommerce\Config\Attributes\Config;
use Markommerce\Config\Command\SetCommand;
use Markommerce\Config\Encryption\NullSecretCipher;
use Markommerce\Config\Registry\ConfigRegistryBuilder;
use Markommerce\Config\Storage\InMemoryConfigStorage;
use Markommerce\ConfigScope\Command\ScopedSetCommand;
use Markommerce\ConfigScope\ScopedConfigWriter;
use Markommerce\ConfigScope\Storage\InMemoryScopedConfigStorage;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;
use Markommerce\Scope\Registry\ScopeRegistryInterface;

// ─── Fixture config classes ───────────────────────────────────────────────────

class ScopedSetCmdStringConfig
{
    #[Config(key: 'scoped-set-cmd/test.storeName')]
    public string $storeName = 'default';
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function makeScopedSetCmdScopeRegistry(array $axisNames = []): ScopeRegistryInterface
{
    return new class ($axisNames) implements ScopeRegistryInterface
    {
        /** @var array<string, ScopeAxis> */
        private array $builtAxes;

        public function __construct(array $axisNames)
        {
            $this->builtAxes = [];
            foreach ($axisNames as $name) {
                $hierarchy = new ScopeHierarchy(['default', 'en', 'fr', '1', '2']);
                $this->builtAxes[$name] = new ScopeAxis(name: $name, hierarchy: $hierarchy, default: 'default');
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

function buildScopedSetCommand(
    InMemoryConfigStorage $globalStorage,
    InMemoryScopedConfigStorage $scopedStorage,
    ScopedFieldRegistry $scopedFieldRegistry,
): ScopedSetCommand {
    $builder = new ConfigRegistryBuilder();
    $registry = $builder->build([ScopedSetCmdStringConfig::class]);

    $writer = new ScopedConfigWriter(
        registry: $registry,
        storage: $globalStorage,
        cipher: new NullSecretCipher(),
        scopedStorage: $scopedStorage,
        scopedFieldRegistry: $scopedFieldRegistry,
    );

    return new ScopedSetCommand(
        registry: $registry,
        writer: $writer,
    );
}

/**
 * @return array{exitCode: int, output: string}
 */
function runScopedSetCommand(ScopedSetCommand $command, string ...$args): array
{
    $stream = fopen('php://memory', 'r+');
    assert($stream !== false);

    $cmdArgs = array_merge(['marko', 'config:set'], $args);
    /** @var list<string> $cmdArgs */
    $input = new Input($cmdArgs);
    $output = new Output($stream);

    $exitCode = $command->execute($input, $output);
    rewind($stream);

    return [
        'exitCode' => $exitCode,
        'output' => (string) stream_get_contents($stream),
    ];
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('carries #[Preference(replaces: SetCommand::class)] on ScopedSetCommand', function (): void {
    $reflection = new ReflectionClass(ScopedSetCommand::class);
    $attributes = $reflection->getAttributes(Preference::class);

    expect($attributes)->not->toBeEmpty();

    $preference = $attributes[0]->newInstance();

    expect($preference->replaces)->toBe(SetCommand::class);
});

it('does NOT carry a #[Command] attribute on ScopedSetCommand (parent\'s #[Command(name: \'config:set\')] is the single source of command-name registration; a duplicate attribute would cause CommandRegistry::register to throw duplicateCommandName)', function (): void {
    $reflection = new ReflectionClass(ScopedSetCommand::class);
    $commandAttributes = $reflection->getAttributes(Command::class);

    expect($commandAttributes)->toBeEmpty();
});

it('parses --scope=axis=value into a ScopeSignature inside ScopedSetCommand execute', function (): void {
    $globalStorage = new InMemoryConfigStorage();
    $scopedStorage = new InMemoryScopedConfigStorage();
    $scopeRegistry = makeScopedSetCmdScopeRegistry(['locale']);
    $scopedFieldRegistry = new ScopedFieldRegistry($scopeRegistry);
    $scopedFieldRegistry->register(ScopedSetCmdStringConfig::class, 'storeName', ['locale']);

    $command = buildScopedSetCommand($globalStorage, $scopedStorage, $scopedFieldRegistry);

    $result = runScopedSetCommand($command, 'scoped-set-cmd/test.storeName', 'french-name', '--scope=locale=fr');

    expect($result['exitCode'])->toBe(0);

    // Verify the override was stored under the parsed signature
    $overrides = $scopedStorage->loadOverrides('scoped-set-cmd/test.storeName');
    expect($overrides)->toHaveKey('locale:fr');
});

it('calls writer.setOverride when --scope is provided and writer.setGlobal otherwise from ScopedSetCommand', function (): void {
    $globalStorage = new InMemoryConfigStorage();
    $scopedStorage = new InMemoryScopedConfigStorage();
    $scopeRegistry = makeScopedSetCmdScopeRegistry(['locale']);
    $scopedFieldRegistry = new ScopedFieldRegistry($scopeRegistry);
    $scopedFieldRegistry->register(ScopedSetCmdStringConfig::class, 'storeName', ['locale']);

    $command = buildScopedSetCommand($globalStorage, $scopedStorage, $scopedFieldRegistry);

    // With --scope: sets override
    $resultWithScope = runScopedSetCommand($command, 'scoped-set-cmd/test.storeName', 'fr-name', '--scope=locale=fr');
    expect($resultWithScope['exitCode'])->toBe(0);
    $overrides = $scopedStorage->loadOverrides('scoped-set-cmd/test.storeName');
    expect($overrides)->toHaveKey('locale:fr');

    // Without --scope: sets global
    $resultWithoutScope = runScopedSetCommand($command, 'scoped-set-cmd/test.storeName', 'global-name');
    expect($resultWithoutScope['exitCode'])->toBe(0);
    $globalRow = $globalStorage->load('scoped-set-cmd/test.storeName');
    expect($globalRow)->not->toBeNull()
        ->and($globalRow->value)->toBe('global-name');
});
