<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Attributes\Preference;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Core\Container\PreferenceRegistry;
use Markommerce\Config\Attributes\Config;
use Markommerce\Config\Casting\ValueCaster;
use Markommerce\Config\Command\ConfigGetCommand;
use Markommerce\Config\Encryption\NullSecretCipher;
use Markommerce\Config\Proxy\ProxyLocator;
use Markommerce\Config\Registry\ConfigRegistry;
use Markommerce\Config\Registry\ConfigRegistryBuilder;
use Markommerce\Config\Storage\InMemoryConfigStorage;
use Markommerce\ConfigScope\Command\ScopedConfigGetCommand;
use Markommerce\ConfigScope\Resolution\OverrideMatcher;
use Markommerce\ConfigScope\ScopedConfigResolver;
use Markommerce\ConfigScope\Storage\InMemoryScopedConfigStorage;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Signature\SignatureCandidateEnumerator;

// ─── Fixture config classes ───────────────────────────────────────────────────

class ScopedGetCmdStringConfig
{
    #[Config(key: 'scoped-get-cmd/test.storeName')]
    public string $storeName = 'default-store';
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function makeScopedGetCmdScopeRegistry(array $axisNames = []): ScopeRegistryInterface
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

/**
 * @return array{registry: ConfigRegistry, resolver: ScopedConfigResolver}
 */
function buildScopedGetCmdResolver(
    InMemoryConfigStorage $globalStorage,
    InMemoryScopedConfigStorage $scopedStorage,
    ScopedFieldRegistry $scopedFieldRegistry,
    ScopeContext $scopeContext,
    ScopeRegistryInterface $scopeRegistry,
): array {
    $builder = new ConfigRegistryBuilder();
    $registry = $builder->build([ScopedGetCmdStringConfig::class]);

    $enumerator = new SignatureCandidateEnumerator($scopeRegistry);
    $overrideMatcher = new OverrideMatcher($enumerator);

    $resolver = new ScopedConfigResolver(
        configRegistry: $registry,
        configStorage: $globalStorage,
        valueCaster: new ValueCaster(),
        secretCipher: new NullSecretCipher(),
        proxyLocator: new ProxyLocator(),
        preferenceRegistry: new PreferenceRegistry(),
        scopedConfigStorage: $scopedStorage,
        overrideMatcher: $overrideMatcher,
        scopeContext: $scopeContext,
        scopedFieldRegistry: $scopedFieldRegistry,
    );

    return ['registry' => $registry, 'resolver' => $resolver];
}

function buildScopedGetCommand(
    ConfigRegistry $configRegistry,
    ScopedConfigResolver $resolver,
    ScopeContext $scopeContext,
): ScopedConfigGetCommand {
    return new ScopedConfigGetCommand(
        configRegistry: $configRegistry,
        resolver: $resolver,
        scopeContext: $scopeContext,
    );
}

/**
 * @return array{exitCode: int, output: string}
 */
function runScopedGetCommand(ScopedConfigGetCommand $command, string ...$args): array
{
    $stream = fopen('php://memory', 'r+');
    assert($stream !== false);

    $cmdArgs = array_merge(['marko', 'config:get'], $args);
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
    'carries #[Preference(replaces: ConfigGetCommand::class)] on ScopedConfigGetCommand and builds a synthetic ScopeContext from --scope=axis=value',
    function (): void {
        $reflection = new ReflectionClass(ScopedConfigGetCommand::class);
        $attributes = $reflection->getAttributes(Preference::class);
    
        expect($attributes)->not->toBeEmpty();
    
        $preference = $attributes[0]->newInstance();
    
        expect($preference->replaces)->toBe(ConfigGetCommand::class);
    }
);

it('does NOT carry a #[Command] attribute on ScopedConfigGetCommand', function (): void {
    $reflection = new ReflectionClass(ScopedConfigGetCommand::class);
    $commandAttributes = $reflection->getAttributes(Command::class);

    expect($commandAttributes)->toBeEmpty();
});

it(
    'returns the resolved override value from ScopedConfigGetCommand when --scope matches a persisted override',
    function (): void {
        $globalStorage = new InMemoryConfigStorage();
        $scopedStorage = new InMemoryScopedConfigStorage();
        $scopeRegistry = makeScopedGetCmdScopeRegistry(['locale']);
        $scopedFieldRegistry = new ScopedFieldRegistry($scopeRegistry);
        $scopedFieldRegistry->register(ScopedGetCmdStringConfig::class, 'storeName', ['locale']);
    
        $scopeContext = new ScopeContext($scopeRegistry);
        ['registry' => $configRegistry, 'resolver' => $resolver] = buildScopedGetCmdResolver(
            $globalStorage,
            $scopedStorage,
            $scopedFieldRegistry,
            $scopeContext,
            $scopeRegistry
        );
        $command = buildScopedGetCommand($configRegistry, $resolver, $scopeContext);
    
        // Pre-seed an override for locale=en
    $scopedStorage->saveOverride('scoped-get-cmd/test.storeName', 'locale:en', 'english-store');
    
        $result = runScopedGetCommand($command, 'scoped-get-cmd/test.storeName', '--scope=locale=en');
    
        expect($result['exitCode'])->toBe(0)
            ->and($result['output'])->toContain('english-store');
    }
);

it(
    'type-hints ScopedConfigResolver (not the base ConfigResolver) on ScopedConfigGetCommand\'s constructor parameter so resolvedAt(...) is statically accessible',
    function (): void {
        $reflection = new ReflectionClass(ScopedConfigGetCommand::class);
        $constructor = $reflection->getConstructor();
    
        assert($constructor !== null);
    
        $resolverParam = array_find(
            $constructor->getParameters(),
            fn ($param) => $param->getName() === 'resolver',
        );
    
        expect($resolverParam)->not->toBeNull();
    
        $type = (string) $resolverParam->getType();
    
        expect($type)->toBe(ScopedConfigResolver::class);
    }
);
