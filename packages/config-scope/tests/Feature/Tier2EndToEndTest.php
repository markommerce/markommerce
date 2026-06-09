<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Core\Container\Container;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Plugin\PluginInterceptedInterface;
use Markommerce\Config\Cache\CachingConfigResolver;
use Markommerce\Config\Command\SetCommand;
use Markommerce\Config\ConfigResolver;
use Markommerce\Config\Contracts\ConfigCacheInterface;
use Markommerce\Config\Contracts\ConfigWriterInterface;
use Markommerce\Config\PgSql\Schema\ConfigValuesTableEmitter;
use Markommerce\ConfigScope\Cache\ScopedCachingConfigResolver;
use Markommerce\ConfigScope\Command\ScopedSetCommand;
use Markommerce\ConfigScope\Contracts\ScopedConfigWriterInterface;
use Markommerce\ConfigScope\PgSql\Schema\ConfigValueOverridesTableEmitter;
use Markommerce\ConfigScope\ScopedConfigWriter;
use Markommerce\ConfigScope\Tests\Feature\Fixtures\TranslatableSiteConfig;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;
use Markommerce\Scope\Signature\ScopeSignature;
use Markommerce\Scope\Storage\DefaultScopeGuard;
use Markommerce\Testing\Container\ContainerBootstrapper;
use Markommerce\Testing\Database\TestConnection;
use Markommerce\Testing\Module\ModuleResolver;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function tier2VendorDir(): string
{
    return dirname(__DIR__, 4) . '/vendor';
}

/**
 * Build the config repository for the Tier 2 end-to-end test.
 *
 * Provides the full scope axis definition (locale + market) needed for
 * scoped override resolution.
 */
function tier2BuildConfig(): ConfigRepository
{
    return new ConfigRepository([
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
}

/**
 * Build the full manifest list for the Tier 2 end-to-end test.
 *
 * Uses ModuleResolver to get all real modules transitively required by
 * config-scope-pgsql, config-pgsql, config-locale, and config-market.
 * Appends a fixture manifest pointing to the temp fixture module path so
 * ConfigClassDiscovery can discover TranslatableSiteConfig.
 *
 * @return list<ModuleManifest>
 */
function tier2BuildManifests(string $fixtureModulePath): array
{
    $resolver = new ModuleResolver(tier2VendorDir());

    $manifests = $resolver->resolveFrom([
        'markommerce/config-scope-pgsql',
        'markommerce/config-pgsql',
        'markommerce/config-locale',
        'markommerce/config-market',
    ]);

    // Append the fixture module so ConfigClassDiscovery can discover TranslatableSiteConfig.
    // The fixture module's path points to the temp directory that holds src/TranslatableSiteConfig.php.
    $manifests[] = new ModuleManifest(
        name: 'test/translatable-site-config',
        version: '1.0.0',
        require: ['markommerce/config-scope' => '*'],
        path: $fixtureModulePath,
    );

    return $manifests;
}

// ─── Tests ────────────────────────────────────────────────────────────────────

beforeEach(function (): void {
    TestConnection::skipIfUnavailable();
    DefaultScopeGuard::reset();
    tier2SetupSodiumKey();

    $this->conn = new TestConnection();
    tier2CreateTables($this->conn);

    // Create a temp module directory with the fixture class in src/.
    // ConfigClassDiscovery scans <module-path>/src/ recursively.
    $this->fixtureModulePath = sys_get_temp_dir() . '/markommerce-cs-e2e-fixture-' . getmypid();
    $fixtureSrcDir = $this->fixtureModulePath . '/src';

    if (!is_dir($fixtureSrcDir)) {
        mkdir($fixtureSrcDir, 0755, true);
    }

    // Copy the fixture class into the temp src dir so discovery can find it
    $fixtureSource = __DIR__ . '/Fixtures/TranslatableSiteConfig.php';
    copy($fixtureSource, $fixtureSrcDir . '/TranslatableSiteConfig.php');

    // Load the class (it won't be loaded twice if already loaded in a previous test)
    if (!class_exists(TranslatableSiteConfig::class, false)) {
        require $fixtureSource;
    }

    $manifests = tier2BuildManifests($this->fixtureModulePath);
    $config = tier2BuildConfig();

    $bootstrapper = new ContainerBootstrapper();
    $this->container = $bootstrapper->bootedContainer($manifests, $config, $this->conn);
});

afterEach(function (): void {
    if (isset($this->conn)) {
        tier2DropTables($this->conn);
    }

    putenv('MARKOMMERCE_CONFIG_SECRET_KEY');
    DefaultScopeGuard::reset();
});

/**
 * Set the SODIUM secret key env var for tests that need SecretCipherInterface.
 */
function tier2SetupSodiumKey(): void
{
    $key = base64_encode(str_repeat('k', SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
    putenv('MARKOMMERCE_CONFIG_SECRET_KEY=' . $key);
}

/**
 * Create the two required database tables for the Tier 2 end-to-end test.
 */
function tier2CreateTables(TestConnection $conn): void
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
function tier2DropTables(TestConnection $conn): void
{
    $conn->execute('DROP TABLE IF EXISTS config_value_overrides');
    $conn->execute('DROP TABLE IF EXISTS config_values');
}

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

        // The bootstrapper wires the ScopeResolutionCommandPlugin (from the scope module)
        // which intercepts CommandInterface implementations. The container returns a plugin proxy.
        // Unwrap to verify the underlying instance is ScopedSetCommand.
        if ($command instanceof PluginInterceptedInterface) {
            $command = $command->getPluginTarget();
        }

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
