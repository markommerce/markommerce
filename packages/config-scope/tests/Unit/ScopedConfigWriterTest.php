<?php

declare(strict_types=1);

use Marko\Core\Attributes\Preference;
use Markommerce\Config\Attributes\Config;
use Markommerce\Config\ConfigWriter;
use Markommerce\Config\Contracts\ConfigWriterInterface;
use Markommerce\Config\Contracts\SecretCipherInterface;
use Markommerce\Config\Encryption\NullSecretCipher;
use Markommerce\Config\Exceptions\ConfigNotFoundException;
use Markommerce\Config\Registry\ConfigRegistryBuilder;
use Markommerce\Config\Storage\InMemoryConfigStorage;
use Markommerce\ConfigScope\Contracts\ScopedConfigWriterInterface;
use Markommerce\ConfigScope\Exceptions\AxisNotDeclaredException;
use Markommerce\ConfigScope\ScopedConfigWriter;
use Markommerce\ConfigScope\Storage\InMemoryScopedConfigStorage;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Signature\ScopeSignature;

// ─── Fixture config classes ────────────────────────────────────────────────────

class ScopedWriterStringConfig
{
    #[Config(key: 'scoped-writer/test.storeName')]
    public string $storeName = 'default';
}

class ScopedWriterScopedConfig
{
    #[Config(key: 'scoped-writer/test.theme')]
    public string $theme = 'default-theme';
}

class ScopedWriterSecretConfig
{
    #[Config(key: 'scoped-writer/test.apiKey', secret: true)]
    public string $apiKey = '';
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * @param array<string, list<string>> $axesMap axis name => scope paths
 */
function makeScopedWriterScopeRegistry(array $axesMap = []): ScopeRegistryInterface
{
    return new class ($axesMap) implements ScopeRegistryInterface
    {
        /** @var array<string, ScopeAxis> */
        private array $builtAxes;

        /** @param array<string, list<string>> $axesMap */
        public function __construct(array $axesMap)
        {
            $this->builtAxes = [];
            foreach ($axesMap as $name => $paths) {
                $default = $paths[0] ?? '__default';
                if (!in_array($default, $paths, true)) {
                    $paths = array_merge([$default], $paths);
                }
                $hierarchy = new ScopeHierarchy($paths);
                $this->builtAxes[$name] = new ScopeAxis(name: $name, hierarchy: $hierarchy, default: $default);
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

function makeScopedWriterScopedFieldRegistry(
    ScopeRegistryInterface $scopeRegistry,
    string $entityClass,
    string $property,
    array $axes,
): ScopedFieldRegistry {
    $registry = new ScopedFieldRegistry($scopeRegistry);
    $registry->register($entityClass, $property, $axes);

    return $registry;
}

function makeScopedWriter(
    InMemoryConfigStorage $globalStorage,
    InMemoryScopedConfigStorage $scopedStorage,
    ScopedFieldRegistry $scopedFieldRegistry,
    array $configClasses,
): ScopedConfigWriter {
    $builder = new ConfigRegistryBuilder();
    $registry = $builder->build($configClasses);

    return new ScopedConfigWriter(
        registry: $registry,
        storage: $globalStorage,
        cipher: new NullSecretCipher(),
        scopedStorage: $scopedStorage,
        scopedFieldRegistry: $scopedFieldRegistry,
    );
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it(
    'declares setOverride and unsetOverride methods on ScopedConfigWriterInterface that extend ConfigWriterInterface',
    function (): void {
        $reflection = new ReflectionClass(ScopedConfigWriterInterface::class);

        expect($reflection->isInterface())->toBeTrue()
            ->and($reflection->isSubclassOf(ConfigWriterInterface::class))->toBeTrue()
            ->and($reflection->hasMethod('setOverride'))->toBeTrue()
            ->and($reflection->hasMethod('unsetOverride'))->toBeTrue();
    },
);

it('carries #[Preference(replaces: ConfigWriter::class)] on ScopedConfigWriter', function (): void {
    $reflection = new ReflectionClass(ScopedConfigWriter::class);
    $attributes = $reflection->getAttributes(Preference::class);

    expect($attributes)->not->toBeEmpty();

    $preference = $attributes[0]->newInstance();

    expect($preference->replaces)->toBe(ConfigWriter::class);
});

it('extends Markommerce\Config\ConfigWriter and implements ScopedConfigWriterInterface', function (): void {
    $reflection = new ReflectionClass(ScopedConfigWriter::class);

    expect($reflection->getParentClass()->getName())->toBe(ConfigWriter::class)
        ->and($reflection->implementsInterface(ScopedConfigWriterInterface::class))->toBeTrue();
});

it(
    'persists a new per-scope override via ScopedConfigWriter setOverride keyed by the signature string',
    function (): void {
        $globalStorage = new InMemoryConfigStorage();
        $scopedStorage = new InMemoryScopedConfigStorage();
        $scopeRegistry = makeScopedWriterScopeRegistry(['locale' => ['en', 'fr']]);
        $scopedFieldRegistry = makeScopedWriterScopedFieldRegistry(
            $scopeRegistry,
            ScopedWriterScopedConfig::class,
            'theme',
            ['locale'],
        );

        $writer = makeScopedWriter(
            $globalStorage,
            $scopedStorage,
            $scopedFieldRegistry,
            [ScopedWriterScopedConfig::class],
        );
        $signature = new ScopeSignature(['locale' => 'en']);

        $writer->setOverride('scoped-writer/test.theme', $signature, 'modern');

        $overrides = $scopedStorage->loadOverrides('scoped-writer/test.theme');
        expect($overrides)->toBe(['locale:en' => 'modern']);
    },
);

it('replaces an existing per-scope override for the same signature on a second setOverride call', function (): void {
    $globalStorage = new InMemoryConfigStorage();
    $scopedStorage = new InMemoryScopedConfigStorage();
    $scopeRegistry = makeScopedWriterScopeRegistry(['locale' => ['en', 'fr']]);
    $scopedFieldRegistry = makeScopedWriterScopedFieldRegistry(
        $scopeRegistry,
        ScopedWriterScopedConfig::class,
        'theme',
        ['locale'],
    );

    $writer = makeScopedWriter(
        $globalStorage,
        $scopedStorage,
        $scopedFieldRegistry,
        [ScopedWriterScopedConfig::class],
    );
    $signature = new ScopeSignature(['locale' => 'en']);

    $writer->setOverride('scoped-writer/test.theme', $signature, 'modern');
    $writer->setOverride('scoped-writer/test.theme', $signature, 'classic');

    $overrides = $scopedStorage->loadOverrides('scoped-writer/test.theme');
    expect($overrides)->toBe(['locale:en' => 'classic']);
});

it(
    'removes a specific per-scope override via ScopedConfigWriter unsetOverride leaving other overrides untouched',
    function (): void {
        $globalStorage = new InMemoryConfigStorage();
        $scopedStorage = new InMemoryScopedConfigStorage();
        $scopeRegistry = makeScopedWriterScopeRegistry(['locale' => ['en', 'fr']]);
        $scopedFieldRegistry = makeScopedWriterScopedFieldRegistry(
            $scopeRegistry,
            ScopedWriterScopedConfig::class,
            'theme',
            ['locale'],
        );

        $writer = makeScopedWriter(
            $globalStorage,
            $scopedStorage,
            $scopedFieldRegistry,
            [ScopedWriterScopedConfig::class],
        );
        $signatureEn = new ScopeSignature(['locale' => 'en']);
        $signatureFr = new ScopeSignature(['locale' => 'fr']);

        $writer->setOverride('scoped-writer/test.theme', $signatureEn, 'modern');
        $writer->setOverride('scoped-writer/test.theme', $signatureFr, 'classic');
        $writer->unsetOverride('scoped-writer/test.theme', $signatureEn);

        $overrides = $scopedStorage->loadOverrides('scoped-writer/test.theme');
        expect($overrides)->toBe(['locale:fr' => 'classic']);
    },
);

it('accepts SecretCipherInterface but does NOT invoke it for non-secret writes', function (): void {
    $globalStorage = new InMemoryConfigStorage();
    $scopedStorage = new InMemoryScopedConfigStorage();
    $scopeRegistry = makeScopedWriterScopeRegistry(['locale' => ['en']]);
    $scopedFieldRegistry = makeScopedWriterScopedFieldRegistry(
        $scopeRegistry,
        ScopedWriterScopedConfig::class,
        'theme',
        ['locale'],
    );

    // NullSecretCipher throws when called — proves cipher is bypassed for non-secret
    $builder = new ConfigRegistryBuilder();
    $registry = $builder->build([ScopedWriterScopedConfig::class]);

    $writer = new ScopedConfigWriter(
        registry: $registry,
        storage: $globalStorage,
        cipher: new NullSecretCipher(),
        scopedStorage: $scopedStorage,
        scopedFieldRegistry: $scopedFieldRegistry,
    );

    $signature = new ScopeSignature(['locale' => 'en']);

    // Should NOT throw, since the cipher is not invoked for non-secret values
    $writer->setOverride('scoped-writer/test.theme', $signature, 'modern');

    $overrides = $scopedStorage->loadOverrides('scoped-writer/test.theme');
    expect($overrides)->toBe(['locale:en' => 'modern']);
});

it('encrypts secret values via SecretCipher before persisting an override', function (): void {
    $globalStorage = new InMemoryConfigStorage();
    $scopedStorage = new InMemoryScopedConfigStorage();
    $scopeRegistry = makeScopedWriterScopeRegistry(['locale' => ['en']]);
    $scopedFieldRegistry = makeScopedWriterScopedFieldRegistry(
        $scopeRegistry,
        ScopedWriterSecretConfig::class,
        'apiKey',
        ['locale'],
    );

    $builder = new ConfigRegistryBuilder();
    $registry = $builder->build([ScopedWriterSecretConfig::class]);

    // Use IdentitySecretCipher-like: encrypt(json_encode($value)) = json_encode($value)
    $identityCipher = new class () implements SecretCipherInterface
    {
        public function encrypt(string $plaintext): string
        {
            return $plaintext;
        }

        public function decrypt(string $ciphertext): string
        {
            return $ciphertext;
        }
    };

    $writer = new ScopedConfigWriter(
        registry: $registry,
        storage: $globalStorage,
        cipher: $identityCipher,
        scopedStorage: $scopedStorage,
        scopedFieldRegistry: $scopedFieldRegistry,
    );

    $signature = new ScopeSignature(['locale' => 'en']);
    $writer->setOverride('scoped-writer/test.apiKey', $signature, 'my-secret-key');

    $overrides = $scopedStorage->loadOverrides('scoped-writer/test.apiKey');

    // IdentitySecretCipher: encrypt(json_encode('my-secret-key')) == json_encode('my-secret-key')
    expect($overrides)->toBe(['locale:en' => json_encode('my-secret-key')]);
});

it(
    'throws AxisNotDeclaredException when setOverride\'s signature uses an axis not registered for the property via ScopedFieldRegistry',
    function (): void {
        $globalStorage = new InMemoryConfigStorage();
        $scopedStorage = new InMemoryScopedConfigStorage();
        $scopeRegistry = makeScopedWriterScopeRegistry(['locale' => ['en'], 'store' => ['1']]);

        // Only 'locale' is declared for the theme property
        $scopedFieldRegistry = makeScopedWriterScopedFieldRegistry(
            $scopeRegistry,
            ScopedWriterScopedConfig::class,
            'theme',
            ['locale'],
        );

        $writer = makeScopedWriter(
            $globalStorage,
            $scopedStorage,
            $scopedFieldRegistry,
            [ScopedWriterScopedConfig::class],
        );

        // Attempt to set override using 'store' axis which is not declared for theme
        $signature = new ScopeSignature(['store' => '1']);

        expect(fn () => $writer->setOverride('scoped-writer/test.theme', $signature, 'modern'))
            ->toThrow(AxisNotDeclaredException::class);
    },
);

it(
    'throws ConfigNotFoundException when ScopedConfigWriter setOverride is called for a key not in the registry',
    function (): void {
        $globalStorage = new InMemoryConfigStorage();
        $scopedStorage = new InMemoryScopedConfigStorage();
        $scopeRegistry = makeScopedWriterScopeRegistry(['locale' => ['en']]);
        $scopedFieldRegistry = new ScopedFieldRegistry($scopeRegistry);

        $writer = makeScopedWriter(
            $globalStorage,
            $scopedStorage,
            $scopedFieldRegistry,
            [ScopedWriterScopedConfig::class],
        );
        $signature = new ScopeSignature(['locale' => 'en']);

        expect(fn () => $writer->setOverride('nonexistent/key.value', $signature, 'test'))
            ->toThrow(ConfigNotFoundException::class);
    },
);
