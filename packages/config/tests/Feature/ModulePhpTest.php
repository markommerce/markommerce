<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Marko\Core\Module\ModuleRepositoryInterface;
use Marko\Core\Path\ProjectPaths;
use Marko\Core\Attributes\Command;
use Markommerce\Config\Cache\CachingConfigResolver;
use Markommerce\Config\Cache\RequestConfigCache;
use Markommerce\Config\Command\ConfigGetCommand;
use Markommerce\Config\Command\ConfigListCommand;
use Markommerce\Config\Command\GenerateCommand;
use Markommerce\Config\Command\SetCommand;
use Markommerce\Config\Command\UnsetCommand;
use Markommerce\Config\ConfigResolver;
use Markommerce\Config\Contracts\ConfigCacheInterface;
use Markommerce\Config\Contracts\ConfigStorageInterface;
use Markommerce\Config\Contracts\ConfigWriterInterface;
use Markommerce\Config\Contracts\SecretCipherInterface;
use Markommerce\Config\Encryption\SodiumSecretCipher;
use Markommerce\Config\Exceptions\InvalidConfigClassException;
use Markommerce\Config\Exceptions\SecretCipherException;
use Markommerce\Config\Middleware\ConfigCacheResetMiddleware;
use Markommerce\Config\Proxy\ProxyAutoloader;
use Markommerce\Config\Proxy\ProxyGenerator;
use Markommerce\Config\Proxy\ProxyLocator;
use Markommerce\Config\Proxy\ProxyWriter;
use Markommerce\Config\Registry\ConfigRegistry;
use Markommerce\Config\Registry\ConfigRegistryBuilder;
use Markommerce\Config\Storage\InMemoryConfigStorage;
use Markommerce\Config\Tests\Fakes\FakeScopeRegistry;

/**
 * Boots a fresh container using the module.php file.
 *
 * @param array<string, list<string>> $scopeAxes
 * @param array<string, mixed> $markoConfig Additional Marko config values (e.g., ['markommerce.config.auto_regenerate' => true])
 * @param list<ModuleManifest> $modules Modules for discovery
 * @param string|null $generatedDir Override the generated proxy directory
 */
function bootModuleContainer(
    array $scopeAxes = [],
    array $markoConfig = [],
    array $modules = [],
    ?string $generatedDir = null,
): ContainerInterface {
    $moduleArray = require dirname(__DIR__, 2) . '/module.php';

    $preferenceRegistry = new PreferenceRegistry();
    $container = new Container($preferenceRegistry);
    $container->instance(ContainerInterface::class, $container);
    $container->instance(PreferenceRegistry::class, $preferenceRegistry);

    // Provide ModuleRepositoryInterface
    $moduleRepository = new ModuleRepository($modules);
    $container->instance(ModuleRepositoryInterface::class, $moduleRepository);

    // Provide ProjectPaths
    // When generatedDir is given as base/var/generated/config, dirname(..., 3) yields base
    $basePath = $generatedDir !== null
        ? dirname($generatedDir, 3)
        : sys_get_temp_dir() . '/markommerce-module-test-' . uniqid();

    $projectPaths = new ProjectPaths($basePath);
    $container->instance(ProjectPaths::class, $projectPaths);

    // Bind a fake scope registry
    $scopeRegistry = new FakeScopeRegistry($scopeAxes);
    $container->instance(\Markommerce\Scope\Registry\ScopeRegistryInterface::class, $scopeRegistry);

    // Bind Marko ConfigRepositoryInterface with a fake that returns markoConfig values
    $fakeConfigRepo = new class ($markoConfig) implements ConfigRepositoryInterface {
        public function __construct(private array $config) {}

        public function get(string $key, ?string $scope = null): mixed
        {
            return $this->config[$key] ?? null;
        }

        public function has(string $key, ?string $scope = null): bool
        {
            return array_key_exists($key, $this->config);
        }

        public function getString(string $key, ?string $scope = null): string
        {
            return (string) ($this->config[$key] ?? '');
        }

        public function getInt(string $key, ?string $scope = null): int
        {
            return (int) ($this->config[$key] ?? 0);
        }

        public function getBool(string $key, ?string $scope = null): bool
        {
            return (bool) ($this->config[$key] ?? false);
        }

        public function getFloat(string $key, ?string $scope = null): float
        {
            return (float) ($this->config[$key] ?? 0.0);
        }

        public function getArray(string $key, ?string $scope = null): array
        {
            return (array) ($this->config[$key] ?? []);
        }

        public function all(?string $scope = null): array
        {
            return $this->config;
        }

        public function withScope(string $scope): ConfigRepositoryInterface
        {
            return $this;
        }
    };
    $container->instance(ConfigRepositoryInterface::class, $fakeConfigRepo);

    // Register bindings from module.php
    foreach ($moduleArray['bindings'] ?? [] as $interface => $implementation) {
        $container->bind($interface, $implementation);
    }

    // Register singletons from module.php
    foreach ($moduleArray['singletons'] ?? [] as $key => $value) {
        if (is_int($key)) {
            $container->singleton($value);
        } else {
            $container->bind($key, $value);
            $container->singleton($key);
        }
    }

    // Call the boot closure
    if (isset($moduleArray['boot']) && $moduleArray['boot'] instanceof Closure) {
        $container->call($moduleArray['boot']);
    }

    return $container;
}

/**
 * Returns a temp directory for generated proxies, cleaned up after the test.
 */
function makeTempProxyDir(): string
{
    $dir = sys_get_temp_dir() . '/markommerce-proxy-test-' . uniqid();
    mkdir($dir, 0755, true);
    return $dir;
}

// ─────────────────────────────────────────────────────────
// Requirements
// ─────────────────────────────────────────────────────────

it('binds ConfigStorageInterface to InMemoryConfigStorage by default', function (): void {
    $container = bootModuleContainer();

    $storage = $container->get(ConfigStorageInterface::class);

    expect($storage)->toBeInstanceOf(InMemoryConfigStorage::class);
})->group('integration-destructive');

it('registers PreferenceRegistry as an instance in the container during boot', function (): void {
    $container = bootModuleContainer();

    $registry1 = $container->get(PreferenceRegistry::class);
    $registry2 = $container->get(PreferenceRegistry::class);

    expect($registry1)->toBeInstanceOf(PreferenceRegistry::class)
        ->and($registry1)->toBe($registry2); // same instance = registered via instance()
})->group('integration-destructive');

it('discovers config classes by scanning module src directories for properties with #[Config]', function (): void {
    // Create a temp module directory with a fixture config class
    $tempModuleDir = sys_get_temp_dir() . '/markommerce-discovery-test-' . uniqid();
    $srcDir = $tempModuleDir . '/src/Config';
    mkdir($srcDir, 0755, true);

    $namespace = 'Markommerce\\Config\\Tests\\TempDiscovery\\Config';
    $className = 'TempDiscoveryConfig' . uniqid('', false);
    $fqn = $namespace . '\\' . $className;

    file_put_contents($srcDir . '/' . $className . '.php', <<<PHP
        <?php
        declare(strict_types=1);
        namespace {$namespace};
        use Markommerce\\Config\\Attributes\\Config;
        class {$className} {
            #[Config(key: 'temp/discovery.value')]
            public string \$value = 'default';
        }
        PHP);

    // Load the class
    require $srcDir . '/' . $className . '.php';

    $manifest = new ModuleManifest(
        name: 'test/temp-discovery',
        version: '1.0.0',
        path: $tempModuleDir,
        source: 'vendor',
    );

    $container = bootModuleContainer(modules: [$manifest]);

    $registry = $container->get(ConfigRegistry::class);

    expect($registry->all())->not->toBeEmpty()
        ->and(array_any(
            $registry->all(),
            fn ($def) => $def->configClass === $fqn,
        ))->toBeTrue();
})->group('integration-destructive');

it('builds the ConfigRegistry at boot from the discovered class list', function (): void {
    // Create a temp module with a config class
    $tempModuleDir = sys_get_temp_dir() . '/markommerce-registry-test-' . uniqid();
    $srcDir = $tempModuleDir . '/src/Config';
    mkdir($srcDir, 0755, true);

    $namespace = 'Markommerce\\Config\\Tests\\TempRegistry\\Config';
    $className = 'TempRegistryConfig' . uniqid('', false);
    $fqn = $namespace . '\\' . $className;
    $configKey = 'temp/registry.' . uniqid('', false);

    file_put_contents($srcDir . '/' . $className . '.php', <<<PHP
        <?php
        declare(strict_types=1);
        namespace {$namespace};
        use Markommerce\\Config\\Attributes\\Config;
        class {$className} {
            #[Config(key: '{$configKey}')]
            public string \$value = 'default';
        }
        PHP);

    require $srcDir . '/' . $className . '.php';

    $manifest = new ModuleManifest(
        name: 'test/temp-registry',
        version: '1.0.0',
        path: $tempModuleDir,
        source: 'vendor',
    );

    $container = bootModuleContainer(modules: [$manifest]);

    $registry = $container->get(ConfigRegistry::class);

    // Should have the definition for our config key
    $definition = $registry->byKey($configKey);

    expect($definition)->not->toBeNull()
        ->and($definition->configClass)->toBe($fqn)
        ->and($definition->key)->toBe($configKey);
})->group('integration-destructive');

it('registers the ProxyAutoloader at boot so generated proxies resolve', function (): void {
    // Create a temp proxy file that the autoloader should be able to load
    $generatedDir = sys_get_temp_dir() . '/markommerce-proxy-resolve-test-' . uniqid() . '/var/generated/config';

    $proxyClass = 'Markommerce\\Config\\Generated\\Markommerce\\Config\\Tests\\ProxyResolve\\BootAutoloadedConfig_Resolved';

    // ProxyAutoloader strips the Generated namespace prefix from the class name for the file path
    // Path = targetDir / (class minus "Markommerce\Config\Generated\") + .php
    $proxyRelativeClass = 'Markommerce\\Config\\Tests\\ProxyResolve\\BootAutoloadedConfig_Resolved';
    $proxyFilePath = $generatedDir . '/' . str_replace('\\', '/', $proxyRelativeClass) . '.php';
    $proxyFileDir = dirname($proxyFilePath);
    mkdir($proxyFileDir, 0755, true);

    file_put_contents($proxyFilePath, <<<'PHP'
        <?php
        declare(strict_types=1);
        namespace Markommerce\Config\Generated\Markommerce\Config\Tests\ProxyResolve;
        class BootAutoloadedConfig_Resolved {}
        PHP);

    $container = bootModuleContainer(generatedDir: $generatedDir);

    // The autoloader should have been registered — class_exists should load it
    expect(class_exists($proxyClass))->toBeTrue();
})->group('integration-destructive');

it('does NOT instantiate SecretCipher at boot — only on first encrypt/decrypt invocation', function (): void {
    // Boot succeeds even when MARKOMMERCE_CONFIG_SECRET_KEY is unset
    putenv('MARKOMMERCE_CONFIG_SECRET_KEY');
    $container = bootModuleContainer();

    // Container booted — cipher binding is registered lazily; no exception yet
    expect($container)->not->toBeNull();
})->group('integration-destructive');

it('throws a loud setup exception at the FIRST secret read/write when MARKOMMERCE_CONFIG_SECRET_KEY is unset', function (): void {
    putenv('MARKOMMERCE_CONFIG_SECRET_KEY');
    $container = bootModuleContainer();

    expect(fn () => $container->get(SecretCipherInterface::class))
        ->toThrow(SecretCipherException::class);
})->group('integration-destructive');

it('verifies the five #[Command]-annotated command classes exist under src/Command/ (Marko auto-discovers them)', function (): void {
    $commandClasses = [
        ConfigListCommand::class,
        ConfigGetCommand::class,
        SetCommand::class,
        UnsetCommand::class,
        GenerateCommand::class,
    ];

    foreach ($commandClasses as $class) {
        $reflection = new ReflectionClass($class);
        $attributes = $reflection->getAttributes(Command::class);

        expect($attributes)->not->toBeEmpty("Command attribute missing on {$class}");
    }

    expect($commandClasses)->toHaveCount(5);
})->group('integration-destructive');

it('registers ConfigCacheResetMiddleware in globalMiddleware so RequestConfigCache is cleared per request', function (): void {
    $moduleArray = require dirname(__DIR__, 2) . '/module.php';

    $middleware = $moduleArray['globalMiddleware'] ?? [];

    $found = array_any(
        $middleware,
        fn ($entry) => isset($entry['class']) && $entry['class'] === ConfigCacheResetMiddleware::class,
    );

    expect($found)->toBeTrue();
})->group('integration-destructive');

it('binds SecretCipherInterface lazily via a closure that reads the 32-byte key from env on first call', function (): void {
    $key = base64_encode(str_repeat('k', SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
    putenv('MARKOMMERCE_CONFIG_SECRET_KEY=' . $key);

    try {
        $container = bootModuleContainer();
        $cipher = $container->get(SecretCipherInterface::class);

        expect($cipher)->toBeInstanceOf(SodiumSecretCipher::class);
    } finally {
        putenv('MARKOMMERCE_CONFIG_SECRET_KEY');
    }
})->group('integration-destructive');

it('binds ConfigResolver to the CachingConfigResolver decorator so all consumers get caching by default', function (): void {
    $key = base64_encode(str_repeat('k', SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
    putenv('MARKOMMERCE_CONFIG_SECRET_KEY=' . $key);

    try {
        $container = bootModuleContainer();
        $resolver = $container->get(ConfigResolver::class);

        expect($resolver)->toBeInstanceOf(CachingConfigResolver::class);
    } finally {
        putenv('MARKOMMERCE_CONFIG_SECRET_KEY');
    }
})->group('integration-destructive');

it('generates a missing proxy at boot when markommerce.config.auto_regenerate is true and the proxy file is absent', function (): void {
    $generatedDir = sys_get_temp_dir() . '/markommerce-regen-missing-test-' . uniqid() . '/var/generated/config';

    $tempModuleDir = sys_get_temp_dir() . '/markommerce-regen-missing-module-' . uniqid();
    $srcDir = $tempModuleDir . '/src/Config';
    mkdir($srcDir, 0755, true);

    $namespace = 'Markommerce\\Config\\Tests\\TempRegenMissing\\Config';
    $className = 'TempRegenMissingConfig' . uniqid('', false);
    $configKey = 'temp/regen-missing.' . uniqid('', false);

    file_put_contents($srcDir . '/' . $className . '.php', <<<PHP
        <?php
        declare(strict_types=1);
        namespace {$namespace};
        use Markommerce\\Config\\Attributes\\Config;
        class {$className} {
            #[Config(key: '{$configKey}')]
            public string \$value = 'default';
        }
        PHP);

    require $srcDir . '/' . $className . '.php';

    $manifest = new ModuleManifest(
        name: 'test/regen-missing',
        version: '1.0.0',
        path: $tempModuleDir,
        source: 'vendor',
    );

    bootModuleContainer(
        modules: [$manifest],
        markoConfig: ['markommerce.config.auto_regenerate' => true],
        generatedDir: $generatedDir,
    );

    $fqn = $namespace . '\\' . $className;
    $locator = new ProxyLocator();
    $proxyClass = $locator->proxyClassFor($fqn);
    $proxyRelative = str_replace('\\', DIRECTORY_SEPARATOR, $proxyClass) . '.php';
    $proxyFile = rtrim($generatedDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $proxyRelative;

    expect(file_exists($proxyFile))->toBeTrue();
})->group('integration-destructive');

it('regenerates a stale proxy at boot when markommerce.config.auto_regenerate is true and the config class source is newer than the proxy file', function (): void {
    $generatedDir = sys_get_temp_dir() . '/markommerce-regen-stale-test-' . uniqid() . '/var/generated/config';

    $tempModuleDir = sys_get_temp_dir() . '/markommerce-regen-stale-module-' . uniqid();
    $srcDir = $tempModuleDir . '/src/Config';
    mkdir($srcDir, 0755, true);

    $namespace = 'Markommerce\\Config\\Tests\\TempRegenStale\\Config';
    $className = 'TempRegenStaleConfig' . uniqid('', false);
    $configKey = 'temp/regen-stale.' . uniqid('', false);
    $sourceFile = $srcDir . '/' . $className . '.php';

    file_put_contents($sourceFile, <<<PHP
        <?php
        declare(strict_types=1);
        namespace {$namespace};
        use Markommerce\\Config\\Attributes\\Config;
        class {$className} {
            #[Config(key: '{$configKey}')]
            public string \$value = 'default';
        }
        PHP);

    require $sourceFile;

    $fqn = $namespace . '\\' . $className;
    $locator = new ProxyLocator();
    $proxyClass = $locator->proxyClassFor($fqn);
    $proxyRelative = str_replace('\\', DIRECTORY_SEPARATOR, $proxyClass) . '.php';
    $proxyFile = rtrim($generatedDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $proxyRelative;

    // Pre-create a stale proxy file (older than the source)
    mkdir(dirname($proxyFile), 0755, true);
    file_put_contents($proxyFile, '<?php // stale');
    touch($proxyFile, time() - 200);
    touch($sourceFile, time() - 100);

    $staleContent = file_get_contents($proxyFile);

    $manifest = new ModuleManifest(
        name: 'test/regen-stale',
        version: '1.0.0',
        path: $tempModuleDir,
        source: 'vendor',
    );

    bootModuleContainer(
        modules: [$manifest],
        markoConfig: ['markommerce.config.auto_regenerate' => true],
        generatedDir: $generatedDir,
    );

    expect(file_get_contents($proxyFile))->not->toBe($staleContent);
})->group('integration-destructive');

it('does NOT regenerate any proxy at boot when markommerce.config.auto_regenerate is false', function (): void {
    $generatedDir = sys_get_temp_dir() . '/markommerce-no-regen-test-' . uniqid() . '/var/generated/config';

    $tempModuleDir = sys_get_temp_dir() . '/markommerce-no-regen-module-' . uniqid();
    $srcDir = $tempModuleDir . '/src/Config';
    mkdir($srcDir, 0755, true);

    $namespace = 'Markommerce\\Config\\Tests\\TempNoRegen\\Config';
    $className = 'TempNoRegenConfig' . uniqid('', false);
    $configKey = 'temp/no-regen.' . uniqid('', false);
    $sourceFile = $srcDir . '/' . $className . '.php';

    file_put_contents($sourceFile, <<<PHP
        <?php
        declare(strict_types=1);
        namespace {$namespace};
        use Markommerce\\Config\\Attributes\\Config;
        class {$className} {
            #[Config(key: '{$configKey}')]
            public string \$value = 'default';
        }
        PHP);

    require $sourceFile;

    $fqn = $namespace . '\\' . $className;
    $locator = new ProxyLocator();
    $proxyClass = $locator->proxyClassFor($fqn);
    $proxyRelative = str_replace('\\', DIRECTORY_SEPARATOR, $proxyClass) . '.php';
    $proxyFile = rtrim($generatedDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $proxyRelative;

    // Pre-create an old proxy file
    mkdir(dirname($proxyFile), 0755, true);
    $sentinelContent = '<?php // sentinel — should not be overwritten';
    file_put_contents($proxyFile, $sentinelContent);

    $manifest = new ModuleManifest(
        name: 'test/no-regen',
        version: '1.0.0',
        path: $tempModuleDir,
        source: 'vendor',
    );

    bootModuleContainer(
        modules: [$manifest],
        markoConfig: ['markommerce.config.auto_regenerate' => false],
        generatedDir: $generatedDir,
    );

    expect(file_get_contents($proxyFile))->toBe($sentinelContent);
})->group('integration-destructive');

it('bubbles up InvalidConfigClassException from dev-mode regeneration so boot fails loudly', function (): void {
    $tempModuleDir = sys_get_temp_dir() . '/markommerce-bad-regen-module-' . uniqid();
    $srcDir = $tempModuleDir . '/src/Config';
    mkdir($srcDir, 0755, true);

    $namespace = 'Markommerce\\Config\\Tests\\TempBadRegen\\Config';
    $className = 'TempBadRegenConfig' . uniqid('', false);
    $configKey = 'temp/bad-regen.' . uniqid('', false);

    // Readonly nullable property: passes ConfigRegistryBuilder (nullable), fails ProxyGenerator (readonly)
    file_put_contents($srcDir . '/' . $className . '.php', <<<PHP
        <?php
        declare(strict_types=1);
        namespace {$namespace};
        use Markommerce\\Config\\Attributes\\Config;
        class {$className} {
            #[Config(key: '{$configKey}')]
            public readonly ?string \$value;
        }
        PHP);

    require $srcDir . '/' . $className . '.php';

    $manifest = new ModuleManifest(
        name: 'test/bad-regen',
        version: '1.0.0',
        path: $tempModuleDir,
        source: 'vendor',
    );

    expect(fn () => bootModuleContainer(
        modules: [$manifest],
        markoConfig: ['markommerce.config.auto_regenerate' => true],
    ))->toThrow(InvalidConfigClassException::class);
})->group('integration-destructive');
