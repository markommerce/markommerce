<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Marko\Core\Module\ModuleRepositoryInterface;
use Marko\Core\Path\ProjectPaths;
use Markommerce\Config\Attributes\Config;
use Markommerce\Config\ConfigResolver;
use Markommerce\Config\Contracts\ConfigStorageInterface;
use Markommerce\Config\Discovery\ConfigClassDiscovery;
use Markommerce\Config\Registry\ConfigRegistry;
use Markommerce\Config\Storage\InMemoryConfigStorage;
use Markommerce\ConfigScope\Cache\ScopedCachingConfigResolver;
use Markommerce\ConfigScope\Contracts\ScopedConfigStorageInterface;
use Markommerce\ConfigScope\Storage\InMemoryScopedConfigStorage;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Build and boot a container for the config-scope module tests.
 *
 * Wires up the scope module (with given axes), the config module, and
 * the config-scope module.php boot closure.
 *
 * @param list<string> $axisNames Scope axis names to configure
 * @param list<ModuleManifest> $extraModules Additional modules for ConfigClassDiscovery
 */
function bootConfigScopeContainer(
    array $axisNames = [],
    array $extraModules = [],
): ContainerInterface {
    // Build a Marko ConfigRepository with scope axes for ScopeRegistryInterface
    $axesConfig = [];
    foreach ($axisNames as $name) {
        $axesConfig[$name] = ['default' => 'default', 'scopes' => ['default' => []]];
    }
    $markoConfig = new ConfigRepository(['scope' => ['axes' => $axesConfig]]);

    $preferenceRegistry = new PreferenceRegistry();
    $container = new Container($preferenceRegistry);
    $container->instance(ContainerInterface::class, $container);
    $container->instance(PreferenceRegistry::class, $preferenceRegistry);
    $container->instance(ConfigRepositoryInterface::class, $markoConfig);

    // Provide ModuleRepositoryInterface for ConfigClassDiscovery
    $moduleRepository = new ModuleRepository($extraModules);
    $container->instance(ModuleRepositoryInterface::class, $moduleRepository);

    // Provide ProjectPaths (required by config module boot for ProxyAutoloader)
    $basePath = sys_get_temp_dir() . '/markommerce-config-scope-test-' . uniqid();
    $container->instance(ProjectPaths::class, new ProjectPaths($basePath));

    // Bind InMemoryConfigStorage as the test double for ConfigStorageInterface
    $container->bind(ConfigStorageInterface::class, InMemoryConfigStorage::class);

    // Bind InMemoryScopedConfigStorage as the test double for ScopedConfigStorageInterface
    $container->bind(ScopedConfigStorageInterface::class, InMemoryScopedConfigStorage::class);

    // Boot scope module bindings/singletons (so ScopeRegistryInterface and ScopedFieldRegistry are available)
    $scopeModule = require dirname(__DIR__, 3) . '/scope/module.php';
    foreach ($scopeModule['singletons'] as $singleton) {
        $container->singleton($singleton);
    }
    foreach ($scopeModule['bindings'] as $interface => $implementation) {
        $container->bind($interface, $implementation);
    }
    // Boot scope module
    if (isset($scopeModule['boot']) && $scopeModule['boot'] instanceof Closure) {
        $container->call($scopeModule['boot']);
    }

    // Boot config module bindings/singletons (so ConfigClassDiscovery, ConfigRegistry, etc. are available)
    $configModule = require dirname(__DIR__, 3) . '/config/module.php';
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
    // Boot config module
    if (isset($configModule['boot']) && $configModule['boot'] instanceof Closure) {
        $container->call($configModule['boot']);
    }

    // Boot config-scope module (the one we're testing)
    $configScopeModule = require dirname(__DIR__, 2) . '/module.php';
    foreach ($configScopeModule['bindings'] ?? [] as $interface => $implementation) {
        $container->bind($interface, $implementation);
    }
    if (isset($configScopeModule['boot']) && $configScopeModule['boot'] instanceof Closure) {
        $container->call($configScopeModule['boot']);
    }

    return $container;
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('walks every config class returned by ConfigClassDiscovery during boot and registers #[Scoped] properties with ScopedFieldRegistry', function (): void {
    $tempModuleDir = sys_get_temp_dir() . '/markommerce-cs-boot-test-' . uniqid();
    $srcDir = $tempModuleDir . '/src/Config';
    mkdir($srcDir, 0755, true);

    $namespace = 'Markommerce\\ConfigScope\\Tests\\TempBoot\\Config';
    $className = 'TempBootConfig' . uniqid('', false);
    $fqn = $namespace . '\\' . $className;

    file_put_contents($srcDir . '/' . $className . '.php', <<<PHP
        <?php
        declare(strict_types=1);
        namespace $namespace;
        use Markommerce\\Config\\Attributes\\Config;
        use Markommerce\\Scope\\Attributes\\Scoped;
        class $className {
            #[Config(key: 'temp/boot.value')]
            #[Scoped(axes: ['locale'])]
            public string \$value = 'default';
        }
        PHP);

    require $srcDir . '/' . $className . '.php';

    $manifest = new ModuleManifest(
        name: 'test/temp-boot',
        version: '1.0.0',
        path: $tempModuleDir,
        source: 'vendor',
    );

    $container = bootConfigScopeContainer(
        axisNames: ['locale'],
        extraModules: [$manifest],
    );

    $registry = $container->get(ScopedFieldRegistry::class);

    expect($registry->axesForProperty($fqn, 'value'))->toBe(['locale']);
})->group('integration-destructive');

it('does not register any axes when a config class has no #[Scoped] property', function (): void {
    $tempModuleDir = sys_get_temp_dir() . '/markommerce-cs-noscoped-test-' . uniqid();
    $srcDir = $tempModuleDir . '/src/Config';
    mkdir($srcDir, 0755, true);

    $namespace = 'Markommerce\\ConfigScope\\Tests\\TempNoScoped\\Config';
    $className = 'TempNoScopedConfig' . uniqid('', false);
    $fqn = $namespace . '\\' . $className;

    file_put_contents($srcDir . '/' . $className . '.php', <<<PHP
        <?php
        declare(strict_types=1);
        namespace $namespace;
        use Markommerce\\Config\\Attributes\\Config;
        class $className {
            #[Config(key: 'temp/noscoped.value')]
            public string \$value = 'default';
        }
        PHP);

    require $srcDir . '/' . $className . '.php';

    $manifest = new ModuleManifest(
        name: 'test/temp-noscoped',
        version: '1.0.0',
        path: $tempModuleDir,
        source: 'vendor',
    );

    $container = bootConfigScopeContainer(
        axisNames: ['locale'],
        extraModules: [$manifest],
    );

    $registry = $container->get(ScopedFieldRegistry::class);

    expect($registry->hasScopedProperties($fqn))->toBeFalse();
})->group('integration-destructive');

it('registers compound axes (e.g. #[Scoped(axes: [\'locale\', \'market\'])]) as a list of axis names', function (): void {
    $tempModuleDir = sys_get_temp_dir() . '/markommerce-cs-compound-test-' . uniqid();
    $srcDir = $tempModuleDir . '/src/Config';
    mkdir($srcDir, 0755, true);

    $namespace = 'Markommerce\\ConfigScope\\Tests\\TempCompound\\Config';
    $className = 'TempCompoundConfig' . uniqid('', false);
    $fqn = $namespace . '\\' . $className;

    file_put_contents($srcDir . '/' . $className . '.php', <<<PHP
        <?php
        declare(strict_types=1);
        namespace $namespace;
        use Markommerce\\Config\\Attributes\\Config;
        use Markommerce\\Scope\\Attributes\\Scoped;
        class $className {
            #[Config(key: 'temp/compound.value')]
            #[Scoped(axes: ['locale', 'market'])]
            public string \$value = 'default';
        }
        PHP);

    require $srcDir . '/' . $className . '.php';

    $manifest = new ModuleManifest(
        name: 'test/temp-compound',
        version: '1.0.0',
        path: $tempModuleDir,
        source: 'vendor',
    );

    $container = bootConfigScopeContainer(
        axisNames: ['locale', 'market'],
        extraModules: [$manifest],
    );

    $registry = $container->get(ScopedFieldRegistry::class);

    expect($registry->axesForProperty($fqn, 'value'))->toBe(['locale', 'market']);
})->group('integration-destructive');

it('throws Markommerce\Scope\Exceptions\UnknownAxisException when a #[Scoped] axis on a discovered config class is not registered with the ScopeRegistry', function (): void {
    $tempModuleDir = sys_get_temp_dir() . '/markommerce-cs-unknownaxis-test-' . uniqid();
    $srcDir = $tempModuleDir . '/src/Config';
    mkdir($srcDir, 0755, true);

    $namespace = 'Markommerce\\ConfigScope\\Tests\\TempUnknownAxis\\Config';
    $className = 'TempUnknownAxisConfig' . uniqid('', false);

    file_put_contents($srcDir . '/' . $className . '.php', <<<PHP
        <?php
        declare(strict_types=1);
        namespace $namespace;
        use Markommerce\\Config\\Attributes\\Config;
        use Markommerce\\Scope\\Attributes\\Scoped;
        class $className {
            #[Config(key: 'temp/unknownaxis.value')]
            #[Scoped(axes: ['nonexistent_axis'])]
            public string \$value = 'default';
        }
        PHP);

    require $srcDir . '/' . $className . '.php';

    $manifest = new ModuleManifest(
        name: 'test/temp-unknownaxis',
        version: '1.0.0',
        path: $tempModuleDir,
        source: 'vendor',
    );

    // No axes registered in scope, but the class references 'nonexistent_axis'
    expect(fn () => bootConfigScopeContainer(
        axisNames: [],
        extraModules: [$manifest],
    ))->toThrow(UnknownAxisException::class);
})->group('integration-destructive');

it('auto-injects ConfigClassDiscovery and ScopedFieldRegistry into the boot closure via container::call', function (): void {
    $injectedDiscovery = null;
    $injectedRegistry = null;

    $tempModuleDir = sys_get_temp_dir() . '/markommerce-cs-inject-test-' . uniqid();
    $srcDir = $tempModuleDir . '/src/Config';
    mkdir($srcDir, 0755, true);

    $namespace = 'Markommerce\\ConfigScope\\Tests\\TempInject\\Config';
    $className = 'TempInjectConfig' . uniqid('', false);

    file_put_contents($srcDir . '/' . $className . '.php', <<<PHP
        <?php
        declare(strict_types=1);
        namespace $namespace;
        use Markommerce\\Config\\Attributes\\Config;
        class $className {
            #[Config(key: 'temp/inject.value')]
            public string \$value = 'default';
        }
        PHP);

    require $srcDir . '/' . $className . '.php';

    $manifest = new ModuleManifest(
        name: 'test/temp-inject',
        version: '1.0.0',
        path: $tempModuleDir,
        source: 'vendor',
    );

    // Verify by reading the actual module.php and confirming it uses ConfigClassDiscovery and ScopedFieldRegistry type hints
    $moduleSource = file_get_contents(dirname(__DIR__, 2) . '/module.php');

    expect($moduleSource)->toContain('ConfigClassDiscovery')
        ->and($moduleSource)->toContain('ScopedFieldRegistry');

    // Boot the container to confirm the closure runs successfully (i.e., injection works)
    $container = bootConfigScopeContainer(
        axisNames: ['locale'],
        extraModules: [$manifest],
    );

    expect($container->get(ScopedFieldRegistry::class))->toBeInstanceOf(ScopedFieldRegistry::class)
        ->and($container->get(ConfigClassDiscovery::class))->toBeInstanceOf(ConfigClassDiscovery::class);
})->group('integration-destructive');

it('returns the ScopedCachingConfigResolver from container::get(ConfigResolver::class) when all module manifests are booted and PreferenceRegistry is wired (verifies the binding wins over the Tier 1 factory + Preference autowiring path)', function (): void {
    // Set the env variable required by SecretCipherInterface
    $key = base64_encode(str_repeat('k', SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
    putenv('MARKOMMERCE_CONFIG_SECRET_KEY=' . $key);

    try {
        $container = bootConfigScopeContainer(axisNames: ['locale']);

        $resolver = $container->get(ConfigResolver::class);

        expect($resolver)->toBeInstanceOf(ScopedCachingConfigResolver::class);
    } finally {
        putenv('MARKOMMERCE_CONFIG_SECRET_KEY');
    }
})->group('integration-destructive');
