<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Core\Module\ModuleManifest;
use Markommerce\Config\ConfigResolver;
use Markommerce\Config\Contracts\ConfigWriterInterface;
use Markommerce\Config\PgSql\Schema\ConfigValuesTableEmitter;
use Markommerce\ConfigScope\Contracts\ScopedConfigWriterInterface;
use Markommerce\ConfigScope\PgSql\Schema\ConfigValueOverridesTableEmitter;
use Markommerce\ConfigScope\Tests\Feature\Fixtures\TranslatableSiteConfig;
use Markommerce\Scope\Context\ScopeContext;
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
 * config-scope, config-pgsql, config-locale, and config-market.
 * Appends a fixture manifest pointing to the temp fixture module path so
 * ConfigClassDiscovery can discover TranslatableSiteConfig.
 *
 * @return list<ModuleManifest>
 */
function tier2BuildManifests(string $fixtureModulePath): array
{
    $resolver = new ModuleResolver(tier2VendorDir());

    $manifests = $resolver->resolveFrom([
        'markommerce/config-scope',
        'markommerce/config',
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
