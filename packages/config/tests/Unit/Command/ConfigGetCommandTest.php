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
use Markommerce\Scope\Attributes\Scoped;

// --- Fixture config classes ---

class GetStringConfig
{
    #[Config(key: 'general/store.name')]
    public string $name = 'default-store';
}

class GetScopedConfig
{
    #[Config(key: 'general/store.locale')]
    #[Scoped(axes: ['store'])]
    public string $locale = 'en';
}

class GetSecretConfig
{
    #[Config(key: 'payment/stripe.secret_key', secret: true)]
    public string $secretKey = '';
}

// --- Helpers ---

use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Registry\ScopeRegistryInterface;

function makeGetScopeRegistry(): ScopeRegistryInterface
{
    return new class () implements ScopeRegistryInterface
    {
        /** @var array<string, list<string>> */
        private array $axesPaths = [
            'store'   => ['default', 'de', 'en', 'fr'],
            'website' => ['default', 'uk', 'us'],
        ];

        public function hasAxis(string $name): bool
        {
            return array_key_exists($name, $this->axesPaths);
        }

        /** @throws UnknownAxisException */
        public function getAxis(string $name): ScopeAxis
        {
            if (!$this->hasAxis($name)) {
                throw UnknownAxisException::forAxis($name);
            }

            return new ScopeAxis(
                name: $name,
                hierarchy: new ScopeHierarchy($this->axesPaths[$name]),
                default: 'default',
            );
        }

        /** @return list<string> */
        public function listAxes(): array
        {
            return array_keys($this->axesPaths);
        }

        /** @throws UnknownAxisException */
        public function getHierarchy(string $axisName): ScopeHierarchy
        {
            return $this->getAxis($axisName)->hierarchy;
        }
    };
}

function makeGetRegistry(): ConfigRegistry
{
    $builder = new ConfigRegistryBuilder();

    return $builder->build(
        [GetStringConfig::class, GetScopedConfig::class, GetSecretConfig::class],
        makeGetScopeRegistry(),
    );
}

function runGetCommand(
    ConfigRegistry $registry,
    InMemoryConfigStorage $storage,
    string ...$args,
): array {
    $stream = fopen('php://memory', 'r+');
    $input = new Input(array_merge(['marko', 'config:get'], $args));
    $output = new Output($stream);

    $scopeRegistry = makeGetScopeRegistry();
    $command = new ConfigGetCommand($registry, $storage, $scopeRegistry);
    $exitCode = $command->execute($input, $output);

    rewind($stream);

    return [
        'exitCode' => $exitCode,
        'output'   => (string) stream_get_contents($stream),
    ];
}

// --- Tests ---

it(
    'does not print decrypted plaintext when the config is #[Config(secret: true)] — instead shows a redacted marker like ***',
    function (): void {
        $registry = makeGetRegistry();
        $storage = new InMemoryConfigStorage();
    
        $storage->compareAndSave('payment/stripe.secret_key', new ConfigRow(
            key: 'payment/stripe.secret_key',
            value: 'sk_live_supersecretvalue',
            overrides: [],
            version: 0,
        ), 0);
    
        $result = runGetCommand($registry, $storage, 'payment/stripe.secret_key');
    
        expect($result['exitCode'])->toBe(0)
            ->and($result['output'])->toContain('***')
            ->and($result['output'])->not->toContain('sk_live_supersecretvalue');
    }
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

it('returns the resolved scoped value for a key when --scope is given', function (): void {
    $registry = makeGetRegistry();
    $storage = new InMemoryConfigStorage();

    $storage->compareAndSave('general/store.locale', new ConfigRow(
        key: 'general/store.locale',
        value: 'en',
        overrides: ['store:de' => 'de_DE'],
        version: 0,
    ), 0);

    $result = runGetCommand($registry, $storage, 'general/store.locale', '--scope=store=de');

    expect($result['exitCode'])->toBe(0)
        ->and($result['output'])->toContain('de_DE');
});

it('returns the resolved global value for a key under an empty scope', function (): void {
    $registry = makeGetRegistry();
    $storage = new InMemoryConfigStorage();

    $storage->compareAndSave('general/store.name', new ConfigRow(
        key: 'general/store.name',
        value: 'My Store',
        overrides: [],
        version: 0,
    ), 0);

    $result = runGetCommand($registry, $storage, 'general/store.name');

    expect($result['exitCode'])->toBe(0)
        ->and($result['output'])->toContain('My Store');
});
