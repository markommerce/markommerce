<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Attributes\Preference;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Markommerce\Config\Attributes\Config;
use Markommerce\Config\Command\UnsetCommand;
use Markommerce\Config\Encryption\NullSecretCipher;
use Markommerce\Config\Registry\ConfigRegistryBuilder;
use Markommerce\Config\Storage\InMemoryConfigStorage;
use Markommerce\ConfigScope\Command\ScopedUnsetCommand;
use Markommerce\ConfigScope\ScopedConfigWriter;
use Markommerce\ConfigScope\Storage\InMemoryScopedConfigStorage;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;
use Markommerce\Scope\Registry\ScopeRegistryInterface;

// ─── Fixture config classes ───────────────────────────────────────────────────

class ScopedUnsetCmdStringConfig
{
    #[Config(key: 'scoped-unset-cmd/test.storeName')]
    public string $storeName = 'default';
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function makeScopedUnsetCmdScopeRegistry(array $axisNames = []): ScopeRegistryInterface
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

function buildScopedUnsetCommand(
    InMemoryConfigStorage $globalStorage,
    InMemoryScopedConfigStorage $scopedStorage,
    ScopedFieldRegistry $scopedFieldRegistry,
): ScopedUnsetCommand {
    $builder = new ConfigRegistryBuilder();
    $registry = $builder->build([ScopedUnsetCmdStringConfig::class]);

    $writer = new ScopedConfigWriter(
        registry: $registry,
        storage: $globalStorage,
        cipher: new NullSecretCipher(),
        scopedStorage: $scopedStorage,
        scopedFieldRegistry: $scopedFieldRegistry,
    );

    return new ScopedUnsetCommand(
        registry: $registry,
        writer: $writer,
    );
}

/**
 * @return array{exitCode: int, output: string}
 */
function runScopedUnsetCommand(ScopedUnsetCommand $command, string ...$args): array
{
    $stream = fopen('php://memory', 'r+');
    assert($stream !== false);

    $cmdArgs = array_merge(['marko', 'config:unset'], $args);
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

it(
    'carries #[Preference(replaces: UnsetCommand::class)] on ScopedUnsetCommand and calls writer.unsetOverride when --scope is provided',
    function (): void {
        $globalStorage = new InMemoryConfigStorage();
        $scopedStorage = new InMemoryScopedConfigStorage();
        $scopeRegistry = makeScopedUnsetCmdScopeRegistry(['locale']);
        $scopedFieldRegistry = new ScopedFieldRegistry($scopeRegistry);
        $scopedFieldRegistry->register(ScopedUnsetCmdStringConfig::class, 'storeName', ['locale']);

        $command = buildScopedUnsetCommand($globalStorage, $scopedStorage, $scopedFieldRegistry);

        // Verify Preference attribute
        $reflection = new ReflectionClass(ScopedUnsetCommand::class);
        $attributes = $reflection->getAttributes(Preference::class);
        expect($attributes)->not->toBeEmpty();
        $preference = $attributes[0]->newInstance();
        expect($preference->replaces)->toBe(UnsetCommand::class);

        // Pre-seed an override
        $scopedStorage->saveOverride('scoped-unset-cmd/test.storeName', 'locale:fr', 'fr-name');

        // Run command with --scope
        $result = runScopedUnsetCommand($command, 'scoped-unset-cmd/test.storeName', '--scope=locale=fr');

        expect($result['exitCode'])->toBe(0);

        // Override should be removed
        $overrides = $scopedStorage->loadOverrides('scoped-unset-cmd/test.storeName');
        expect($overrides)->not->toHaveKey('locale:fr');
    },
);

it('does NOT carry a #[Command] attribute on ScopedUnsetCommand', function (): void {
    $reflection = new ReflectionClass(ScopedUnsetCommand::class);
    $commandAttributes = $reflection->getAttributes(Command::class);

    expect($commandAttributes)->toBeEmpty();
});
