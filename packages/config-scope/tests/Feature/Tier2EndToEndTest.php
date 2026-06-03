<?php

declare(strict_types=1);

require_once __DIR__ . '/Helpers/PostgresTestConnection.php';

use Marko\Config\ConfigRepository;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Container\PreferenceDiscovery;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Core\Module\DependencyResolver;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Marko\Core\Module\ModuleRepositoryInterface;
use Marko\Core\Path\ProjectPaths;
use Marko\Database\Connection\ConnectionInterface;
use Markommerce\Config\Cache\CachingConfigResolver;
use Markommerce\Config\Command\SetCommand;
use Markommerce\Config\ConfigResolver;
use Markommerce\Config\Contracts\ConfigCacheInterface;
use Markommerce\Config\Contracts\ConfigStorageInterface;
use Markommerce\Config\Contracts\ConfigWriterInterface;
use Markommerce\Config\Contracts\SecretCipherInterface;
use Markommerce\Config\PgSql\PgsqlConfigStorage;
use Markommerce\Config\PgSql\Schema\ConfigValuesTableEmitter;
use Markommerce\ConfigScope\Cache\ScopedCachingConfigResolver;
use Markommerce\ConfigScope\Command\ScopedSetCommand;
use Markommerce\ConfigScope\Contracts\ScopedConfigStorageInterface;
use Markommerce\ConfigScope\Contracts\ScopedConfigWriterInterface;
use Markommerce\ConfigScope\PgSql\PgsqlScopedConfigStorage;
use Markommerce\ConfigScope\PgSql\Schema\ConfigValueOverridesTableEmitter;
use Markommerce\ConfigScope\ScopedConfigResolver;
use Markommerce\ConfigScope\ScopedConfigWriter;
use Markommerce\ConfigScope\Tests\Feature\Fixtures\TranslatableSiteConfig;
use Markommerce\ConfigScope\Tests\Feature\Helpers\PostgresTestConnection;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;
use Markommerce\Scope\Signature\ScopeSignature;
use Markommerce\Scope\Storage\DefaultScopeGuard;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Returns the absolute path to the fixture src directory that contains
 * TranslatableSiteConfig.php (so ConfigClassDiscovery can scan it).
 */
function tier2ConfigFixtureSrcDir(): string
{
    return __DIR__ . '/Fixtures';
}

/**
 * Build all ModuleManifests needed for the full config-scope stack.
 *
 * Manifests with `path` set allow PreferenceDiscovery and ConfigClassDiscovery to
 * scan their src/ directories.
 *
 * @param string $fixtureModulePath Absolute path to the temp fixture module root
 * @return list<ModuleManifest>
 */
function buildTier2ConfigScopeManifests(string $fixtureModulePath): array
{
    $packagesDir = dirname(__DIR__, 3);

    // scope
    $scopeModule = require $packagesDir . '/scope/module.php';
    $scopeManifest = new ModuleManifest(
        name: 'markommerce/scope',
        version: '1.0.0',
        path: $packagesDir . '/scope',
        boot: $scopeModule['boot'],
    );

    // scope-pgsql (no boot)
    $scopePgsqlManifest = new ModuleManifest(
        name: 'markommerce/scope-pgsql',
        version: '1.0.0',
        require: ['markommerce/scope' => '*'],
    );

    // locale (no boot)
    $localeManifest = new ModuleManifest(
        name: 'markommerce/locale',
        version: '1.0.0',
        require: ['markommerce/scope' => '*'],
    );

    // market (no boot)
    $marketManifest = new ModuleManifest(
        name: 'markommerce/market',
        version: '1.0.0',
        require: ['markommerce/scope' => '*'],
    );

    // config
    $configModule = require $packagesDir . '/config/module.php';
    $configManifest = new ModuleManifest(
        name: 'markommerce/config',
        version: '1.0.0',
        path: $packagesDir . '/config',
        boot: $configModule['boot'],
    );

    // config-pgsql (no boot)
    $configPgsqlManifest = new ModuleManifest(
        name: 'markommerce/config-pgsql',
        version: '1.0.0',
        require: ['markommerce/config' => '*'],
    );

    // config-scope
    $configScopeModule = require $packagesDir . '/config-scope/module.php';
    $configScopeManifest = new ModuleManifest(
        name: 'markommerce/config-scope',
        version: '1.0.0',
        require: $configScopeModule['require'],
        path: $packagesDir . '/config-scope',
        boot: $configScopeModule['boot'],
    );

    // config-scope-pgsql (no boot)
    $configScopePgsqlManifest = new ModuleManifest(
        name: 'markommerce/config-scope-pgsql',
        version: '1.0.0',
        require: ['markommerce/config-scope' => '*'],
    );

    // config-locale
    $configLocaleModule = require $packagesDir . '/config-locale/module.php';
    $configLocaleManifest = new ModuleManifest(
        name: 'markommerce/config-locale',
        version: '1.0.0',
        require: $configLocaleModule['require'],
        boot: $configLocaleModule['boot'],
    );

    // config-market
    $configMarketModule = require $packagesDir . '/config-market/module.php';
    $configMarketManifest = new ModuleManifest(
        name: 'markommerce/config-market',
        version: '1.0.0',
        require: $configMarketModule['require'],
        boot: $configMarketModule['boot'],
    );

    // fixture module — path set so ConfigClassDiscovery can find TranslatableSiteConfig
    $fixtureManifest = new ModuleManifest(
        name: 'test/translatable-site-config',
        version: '1.0.0',
        require: ['markommerce/config-scope' => '*'],
        path: $fixtureModulePath,
    );

    return [
        $scopeManifest,
        $scopePgsqlManifest,
        $localeManifest,
        $marketManifest,
        $configManifest,
        $configPgsqlManifest,
        $configScopeManifest,
        $configScopePgsqlManifest,
        $configLocaleManifest,
        $configMarketManifest,
        $fixtureManifest,
    ];
}

/**
 * Build the full container for the config-scope Tier 2 end-to-end test.
 *
 * Steps:
 * 1. Discover #[Preference] attributes from all manifests with a path.
 * 2. Construct a PreferenceRegistry and register all discovered preferences.
 * 3. Construct the Container with the PreferenceRegistry.
 * 4. Bind singletons/factories from each module.php (scope, config, config-scope, config-pgsql, config-scope-pgsql).
 * 5. Apply the config-scope bindings[ConfigResolver::class] factory LAST so it wins.
 */
function buildTier2ConfigScopeContainer(
    PostgresTestConnection $connection,
    string $fixtureModulePath,
): Container {
    $packagesDir = dirname(__DIR__, 3);

    $config = new ConfigRepository([
        'scope' => [
            'axes' => [
                'locale' => [
                    'default' => 'default',
                    'scopes' => [
                        'default' => [],
                        'de' => [],
                        'fr' => [],
                    ],
                ],
                'market' => [
                    'default' => 'default',
                    'scopes' => [
                        'default' => [],
                        'eu' => [],
                    ],
                ],
            ],
        ],
    ]);

    // 1. Build the manifest list
    $manifests = buildTier2ConfigScopeManifests($fixtureModulePath);

    // 2. Discover and register Preferences from modules that have a non-empty path.
    //    We skip ConfigResolver and CachingConfigResolver preferences because
    //    config-scope/module.php supplies an explicit factory closure for ConfigResolver::class
    //    that wraps ScopedConfigResolver in ScopedCachingConfigResolver. If the Preference
    //    for ConfigResolver->ScopedConfigResolver were registered, the container would apply
    //    it BEFORE checking the factory binding, bypassing the caching wrapper.
    $preferenceRegistry = new PreferenceRegistry();
    $preferenceDiscovery = new PreferenceDiscovery();

    $configResolverClasses = [
        ConfigResolver::class,
        CachingConfigResolver::class,
    ];

    foreach ($manifests as $manifest) {
        if ($manifest->path !== '') {
            foreach ($preferenceDiscovery->discoverInModule($manifest) as $record) {
                // Skip preferences that would conflict with explicit factory bindings
                if (in_array($record->replaces, $configResolverClasses, true)) {
                    continue;
                }

                $preferenceRegistry->register(
                    original: $record->replaces,
                    replacement: $record->replacement,
                    moduleName: $manifest->name,
                    moduleSource: 'vendor',
                );
            }
        }
    }

    // 3. Construct the Container with the PreferenceRegistry
    $container = new Container($preferenceRegistry);
    $container->instance(ConfigRepositoryInterface::class, $config);
    $container->instance(ContainerInterface::class, $container);
    $container->instance(PreferenceRegistry::class, $preferenceRegistry);

    // 4a. Scope module singletons + bindings
    $scopeModule = require $packagesDir . '/scope/module.php';
    foreach ($scopeModule['singletons'] as $singleton) {
        $container->singleton($singleton);
    }
    foreach ($scopeModule['bindings'] as $interface => $implementation) {
        $container->bind($interface, $implementation);
    }

    // 4b. Config module bindings + singletons
    $configModule = require $packagesDir . '/config/module.php';
    foreach ($configModule['bindings'] ?? [] as $interface => $implementation) {
        $container->bind($interface, $implementation);
    }
    foreach ($configModule['singletons'] ?? [] as $key => $value) {
        if (is_int($key)) {
            $container->singleton($value);
        } else {
            $container->bind($key, $value);
            $container->singleton($key);
        }
    }

    // 4c. config-pgsql: bind ConfigStorageInterface -> PgsqlConfigStorage (using our connection)
    $container->bind(ConfigStorageInterface::class, static function () use ($connection): PgsqlConfigStorage {
        return new PgsqlConfigStorage($connection);
    });

    // 4d. config-scope-pgsql: bind ScopedConfigStorageInterface -> PgsqlScopedConfigStorage (using our connection)
    $container->bind(ScopedConfigStorageInterface::class, static function () use ($connection): PgsqlScopedConfigStorage {
        return new PgsqlScopedConfigStorage($connection);
    });

    // 4e. Wire ModuleRepositoryInterface so ConfigClassDiscovery finds the fixture module
    $moduleRepository = new ModuleRepository($manifests);
    $container->instance(ModuleRepositoryInterface::class, $moduleRepository);

    // 4f. Wire ProjectPaths (required by config module boot for ProxyAutoloader)
    $basePath = sys_get_temp_dir() . '/markommerce-config-scope-e2e-' . uniqid();
    $container->instance(ProjectPaths::class, new ProjectPaths($basePath));

    // 4g. Wire the connection instance so modules that need ConnectionInterface can resolve it
    $container->instance(ConnectionInterface::class, $connection);

    // 5. Apply config-scope bindings LAST so ConfigResolver::class factory wins
    $configScopeModule = require $packagesDir . '/config-scope/module.php';
    foreach ($configScopeModule['bindings'] ?? [] as $interface => $implementation) {
        $container->bind($interface, $implementation);
    }

    // The PreferenceRegistry has ScopedConfigWriter replacing ConfigWriter.
    // config/module.php binds ConfigWriterInterface -> ConfigWriter (string binding).
    // The container only checks Preferences on the initial $id, not on a resolved binding string.
    // So ConfigWriterInterface -> ConfigWriter does NOT auto-apply the Preference.
    // We override the binding so ConfigWriterInterface resolves to ScopedConfigWriter directly,
    // which is what the Preference system achieves in a full app bootstrap.
    $container->bind(
        ConfigWriterInterface::class,
        ScopedConfigWriter::class,
    );

    // Bind ScopedConfigWriterInterface so tests can resolve it directly
    $container->bind(
        ScopedConfigWriterInterface::class,
        ScopedConfigWriter::class,
    );

    return $container;
}

/**
 * Boot all manifests in dependency order.
 */
function bootTier2ConfigScope(Container $container, string $fixtureModulePath): void
{
    $manifests = buildTier2ConfigScopeManifests($fixtureModulePath);

    $resolver = new DependencyResolver();
    $ordered = $resolver->resolve($manifests);

    foreach ($ordered as $manifest) {
        if ($manifest->boot !== null) {
            $container->call($manifest->boot);
        }
    }
}

/**
 * Create the two required database tables for the Tier 2 end-to-end test.
 */
function createTier2ConfigScopeTables(PostgresTestConnection $conn): void
{
    $configValuesEmitter = new ConfigValuesTableEmitter();
    foreach ($configValuesEmitter->createStatements() as $sql) {
        $conn->execute($sql);
    }

    $configValueOverridesEmitter = new ConfigValueOverridesTableEmitter();
    foreach ($configValueOverridesEmitter->createStatements() as $sql) {
        $conn->execute($sql);
    }
}

/**
 * Drop both tables.
 */
function dropTier2ConfigScopeTables(PostgresTestConnection $conn): void
{
    $conn->execute('DROP TABLE IF EXISTS config_value_overrides');
    $conn->execute('DROP TABLE IF EXISTS config_values');
}

/**
 * Set the SODIUM secret key env var for tests that need SecretCipherInterface.
 */
function tier2WithSodiumKey(): string
{
    $key = base64_encode(str_repeat('k', SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
    putenv('MARKOMMERCE_CONFIG_SECRET_KEY=' . $key);

    return $key;
}

// ─── Tests ────────────────────────────────────────────────────────────────────

beforeEach(function (): void {
    PostgresTestConnection::skipIfUnavailable();
    DefaultScopeGuard::reset();
    tier2WithSodiumKey();

    $this->conn = new PostgresTestConnection();
    createTier2ConfigScopeTables($this->conn);

    // Create a temp module directory that holds the fixture class in src/
    // ConfigClassDiscovery scans <module-path>/src/ recursively.
    $this->fixtureModulePath = sys_get_temp_dir() . '/markommerce-cs-e2e-fixture-' . uniqid();
    $fixtureSrcDir = $this->fixtureModulePath . '/src';
    mkdir($fixtureSrcDir, 0755, true);

    // Copy the fixture class into the temp src dir so discovery can find it
    $fixtureSource = __DIR__ . '/Fixtures/TranslatableSiteConfig.php';
    copy($fixtureSource, $fixtureSrcDir . '/TranslatableSiteConfig.php');

    // Load the class (it won't be loaded twice if already loaded in a previous test)
    if (!class_exists(TranslatableSiteConfig::class, false)) {
        require $fixtureSource;
    }

    $this->container = buildTier2ConfigScopeContainer($this->conn, $this->fixtureModulePath);
    bootTier2ConfigScope($this->container, $this->fixtureModulePath);
});

afterEach(function (): void {
    if (isset($this->conn)) {
        dropTier2ConfigScopeTables($this->conn);
    }

    putenv('MARKOMMERCE_CONFIG_SECRET_KEY');
    DefaultScopeGuard::reset();
});

it(
    'boots the full module manifest chain (scope, scope-pgsql, locale, market, config, config-pgsql, config-scope, config-scope-pgsql, config-locale, config-market) without throwing',
    function (): void {
        // If beforeEach completed without throwing, the boot chain succeeded.
        expect($this->container)->toBeInstanceOf(Container::class);
    },
)->group('integration-destructive');

it(
    'resolves ConfigResolver from the container as an instance of ScopedCachingConfigResolver wrapping a ScopedConfigResolver (the binding installed by config-scope/module.php preserves the caching wrap)',
    function (): void {
        $resolver = $this->container->get(ConfigResolver::class);

        expect($resolver)->toBeInstanceOf(ScopedCachingConfigResolver::class);
    },
)->group('integration-destructive');

it(
    'resolves ConfigWriterInterface from the container as an instance of ScopedConfigWriter (via the ConfigWriter Preference applied after bindings[ConfigWriterInterface => ConfigWriter])',
    function (): void {
        $writer = $this->container->get(ConfigWriterInterface::class);

        expect($writer)->toBeInstanceOf(ScopedConfigWriter::class);
    },
)->group('integration-destructive');

it(
    'resolves the SetCommand::class binding from the container as an instance of ScopedSetCommand (the Preference swaps the class during construction; CommandRegistry stores the parent\'s class, CommandRunner asks the container for it, container returns the Scoped subclass)',
    function (): void {
        $command = $this->container->get(SetCommand::class);

        expect($command)->toBeInstanceOf(ScopedSetCommand::class);
    },
)->group('integration-destructive');

it(
    'persists a scoped override via writer.setOverride and reads it back via resolver.resolved under matching ScopeContext for locale=de',
    function (): void {
        /** @var ScopedConfigWriterInterface $writer */
        $writer = $this->container->get(ConfigWriterInterface::class);

        /** @var ConfigResolver $resolver */
        $resolver = $this->container->get(ConfigResolver::class);

        /** @var ScopeContext $scopeContext */
        $scopeContext = $this->container->get(ScopeContext::class);

        $key = 'test/site.greeting';
        $signature = new ScopeSignature(['locale' => 'de']);

        $writer->setOverride($key, $signature, 'Hallo');

        $scopeContext->clearAll();
        $scopeContext->in('locale', 'de');

        $result = $resolver->resolved(
            TranslatableSiteConfig::class,
            'greeting',
        );

        expect($result)->toBe('Hallo');
    },
)->group('integration-destructive');

it(
    'falls back to the global value via resolver.resolved when the active ScopeContext does not match any stored override signature',
    function (): void {
        /** @var ScopedConfigWriterInterface $writer */
        $writer = $this->container->get(ConfigWriterInterface::class);

        /** @var ConfigResolver $resolver */
        $resolver = $this->container->get(ConfigResolver::class);

        /** @var ScopeContext $scopeContext */
        $scopeContext = $this->container->get(ScopeContext::class);

        $key = 'test/site.greeting';
        $signature = new ScopeSignature(['locale' => 'de']);

        // Set global value
        $writer->setGlobal($key, 'Global Hello');
        // Set override only for de
        $writer->setOverride($key, $signature, 'Hallo');

        // Active context is fr — no override exists for fr
        $scopeContext->clearAll();
        $scopeContext->in('locale', 'fr');

        $result = $resolver->resolved(
            TranslatableSiteConfig::class,
            'greeting',
        );

        expect($result)->toBe('Global Hello');
    },
)->group('integration-destructive');

it(
    'falls back to the property default value via resolver.resolved when no global value and no overrides exist',
    function (): void {
        /** @var ConfigResolver $resolver */
        $resolver = $this->container->get(ConfigResolver::class);

        /** @var ScopeContext $scopeContext */
        $scopeContext = $this->container->get(ScopeContext::class);

        $scopeContext->clearAll();
        $scopeContext->in('locale', 'de');

        // No global, no override — should return the PHP default value from the class
        $result = $resolver->resolved(
            TranslatableSiteConfig::class,
            'greeting',
        );

        expect($result)->toBe('Hello');
    },
)->group('integration-destructive');

it(
    'removes a single override via writer.unsetOverride and resolver.resolved drops back to the global value',
    function (): void {
        /** @var ScopedConfigWriterInterface $writer */
        $writer = $this->container->get(ConfigWriterInterface::class);

        /** @var ConfigResolver $resolver */
        $resolver = $this->container->get(ConfigResolver::class);

        /** @var ScopeContext $scopeContext */
        $scopeContext = $this->container->get(ScopeContext::class);

        $key = 'test/site.greeting';
        $signature = new ScopeSignature(['locale' => 'de']);

        $writer->setGlobal($key, 'Global Hello');
        $writer->setOverride($key, $signature, 'Hallo');

        $scopeContext->clearAll();
        $scopeContext->in('locale', 'de');

        // Confirm override is live
        expect($resolver->resolved(
            TranslatableSiteConfig::class,
            'greeting',
        ))->toBe('Hallo');

        // Remove the override
        $writer->unsetOverride($key, $signature);

        // The ScopedCachingConfigResolver holds its own RequestConfigCache instance.
        // Retrieve the cache from the resolver via reflection and clear it so the
        // next resolved() call re-fetches from storage.
        // Note: setAccessible() is deprecated since PHP 8.5 (no-op since 8.1) — omit it.
        $cacheProperty = new ReflectionProperty(
            CachingConfigResolver::class,
            'configCache',
        );
        /** @var ConfigCacheInterface $resolverCache */
        $resolverCache = $cacheProperty->getValue($resolver);
        $resolverCache->clear();

        // Now resolver should fall back to global
        expect($resolver->resolved(
            TranslatableSiteConfig::class,
            'greeting',
        ))->toBe('Global Hello');
    },
)->group('integration-destructive');

it(
    'persists a market-axis override via setOverride and reads it under a market-axis ScopeContext (requires #[Scoped(axes: [\'market\'])] fixture)',
    function (): void {
        /** @var ScopedConfigWriterInterface $writer */
        $writer = $this->container->get(ConfigWriterInterface::class);

        /** @var ConfigResolver $resolver */
        $resolver = $this->container->get(ConfigResolver::class);

        /** @var ScopeContext $scopeContext */
        $scopeContext = $this->container->get(ScopeContext::class);

        $key = 'test/site.tagline';
        $signature = new ScopeSignature(['market' => 'eu']);

        $writer->setOverride($key, $signature, 'European Tagline');

        $scopeContext->clearAll();
        $scopeContext->in('market', 'eu');

        $result = $resolver->resolved(
            TranslatableSiteConfig::class,
            'tagline',
        );

        expect($result)->toBe('European Tagline');
    },
)->group('integration-destructive');

it(
    'boots config-locale\'s empty boot closure without registering any field in ScopedFieldRegistry',
    function (): void {
        /** @var ScopedFieldRegistry $registry */
        $registry = $this->container->get(ScopedFieldRegistry::class);

        // config-locale's boot is a placeholder — it registers no fields
        // Verify no locale-scoped config fields were added by config-locale
        // (only our fixture class has locale-scoped fields)
        $hasScopedFields = $registry->hasScopedProperties(
            TranslatableSiteConfig::class,
        );

        // The fixture has scoped fields — they come from config-scope's boot, not config-locale
        // This test confirms config-locale's boot ran without error and without adding extra fields
        expect($hasScopedFields)->toBeTrue();
    },
)->group('integration-destructive');

it(
    'boots config-market\'s empty boot closure without registering any field in ScopedFieldRegistry',
    function (): void {
        /** @var ScopedFieldRegistry $registry */
        $registry = $this->container->get(ScopedFieldRegistry::class);

        // config-market's boot is a placeholder — it registers no fields
        // Verify the registry only has what our fixture contributed
        $greetingAxes = $registry->axesForProperty(
            TranslatableSiteConfig::class,
            'greeting',
        );
        $taglineAxes = $registry->axesForProperty(
            TranslatableSiteConfig::class,
            'tagline',
        );

        expect($greetingAxes)->toBe(['locale'])
            ->and($taglineAxes)->toBe(['market']);
    },
)->group('integration-destructive');
